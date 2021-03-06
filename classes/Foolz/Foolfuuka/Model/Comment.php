<?php

namespace Foolz\Foolfuuka\Model;

use Foolz\Foolframe\Model\Config;
use Foolz\Foolframe\Model\DoctrineConnection;
use Foolz\Cache\Cache;
use Foolz\Foolframe\Model\Context;
use Foolz\Foolframe\Model\Logger;
use Foolz\Foolframe\Model\Model;
use Foolz\Foolframe\Model\Uri;
use Foolz\Plugin\Hook;
use Foolz\Plugin\PlugSuit;
use Foolz\Theme\Builder;

class CommentException extends \Exception {}
class CommentDeleteWrongPassException extends CommentException {}

class Comment extends Model
{
    use PlugSuit;

    /**
     * If the backlinks must be full URLs or just the hash
     * Notice: this is global because it's used in a PHP callback
     *
     * @var  boolean
     */
    protected $_backlinks_hash_only_url = false;

    /**
     * Stores a Radix object for the link processing
     *
     * @var  null|\Foolz\Foolfuuka\Model\Radix
     */
    protected $_current_radix_for_prc = null;

    /**
     * The controller method, usually "thread" or "last/xx"
     *
     * @var  string
     */
    public $_controller_method = 'thread';

    /**
     * A reference to the theme that must be used to build the API response
     *
     * @var null|\Foolz\Theme\Theme
     */
    public $_theme = null;

    /**
     * The bbcode parser object when created
     *
     * @var null|object
     */
    protected static $_bbcode_parser = null;

    /**
     * @var DoctrineConnection
     */
    protected $dc;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Uri
     */
    protected $uri;

    /**
     * @var CommentFactory
     */
    protected $comment_factory;

    /**
     * @var MediaFactory
     */
    protected $media_factory;

    /**
     * @var RadixCollection
     */
    protected $radix_coll;

    /**
     * @var ReportCollection
     */
    protected $report_coll;

    /**
     * Switches
     *
     * @var  array
     */
    protected $_options = [
        'realtime' => false,
        'clean' => true,
        'prefetch_backlinks' => true,
        'force_entries' => false
    ];

    /**
     * Entries that should be included in API requests
     *
     * @var  array
     */
    public $_forced_entries = [
        'title_processed' => 'getTitleProcessed',
        'name_processed' => 'getNameProcessed',
        'email_processed' => 'getEmailProcessed',
        'trip_processed' => 'getTripProcessed',
        'poster_hash_processed' => 'getPosterHashProcessed',
        'original_timestamp' => 'getOriginalTimestamp',
        'fourchan_date' => 'getFourchanDate',
        'comment_sanitized' => 'getCommentSanitized',
        'comment_processed' => 'getCommentProcessed',
        'poster_country_name_processed' => 'getPosterCountryNameProcessed'
    ];

    public $recaptcha_challenge = null;
    public $recaptcha_response = null;

    public $fourchan_date = false;
    public $comment_sanitized = false;
    public $comment_processed = false;
    public $formatted = false;
    public $reports = false;
    public $title_processed = false;
    public $name_processed = false;
    public $email_processed = false;
    public $trip_processed = false;
    public $poster_hash_processed = false;
    public $poster_country_name_processed = false;

    public $radix = null;

    public $doc_id = 0;
    public $poster_ip = null;
    public $num = 0;
    public $subnum = 0;
    public $thread_num = 0;
    public $op = 0;
    public $timestamp = 0;
    public $timestamp_expired = 0;
    public $capcode = 'N';
    public $email = null;
    public $name = null;
    public $trip = null;
    public $title = null;
    public $comment = null;
    public $delpass = null;
    public $poster_hash = null;
    public $poster_country = null;

    public $sticky = false;
    public $locked = false;

    public $media = null;
    public $extra = null;

    public function __construct(Context $context, $post, $board, $options = [])
    {
        parent::__construct($context);

        $this->dc = $context->getService('doctrine');
        $this->config = $context->getService('config');
        $this->logger = $context->getService('logger');
        $this->uri = $context->getService('uri');
        $this->comment_factory = $context->getService('foolfuuka.comment_factory');
        $this->media_factory = $context->getService('foolfuuka.media_factory');
        $this->ban_factory = $context->getService('foolfuuka.ban_factory');
        $this->radix_coll = $context->getService('foolfuuka.radix_collection');
        $this->report_coll = $context->getService('foolfuuka.report_collection');

        $this->radix = $board;

        $media_fields = Media::getFields();
        $extra_fields = Extra::getFields();
        $media = new \stdClass();
        $extra = new \stdClass();
        $do_media = false;
        $do_extra = false;

        foreach ($post as $key => $value) {
            if (!in_array($key, $media_fields) && !in_array($key, $extra_fields)) {
                $this->$key = $value;
            } elseif (in_array($key, $extra_fields)) {
                $do_extra = true;
                $extra->$key = $value;
            } else {
                $do_media = true;
                $media->$key = $value;
            }
        }

        if ($do_media && isset($media->media_id) && $media->media_id > 0) {
            $this->media = new Media($this->getContext(), $media, $this->radix, $this->op);
        } else {
            $this->media = null;
        }

        $this->extra = new Extra($this->getContext(), $extra, $this->radix);

        foreach ($options as $key => $value) {
            if ($key == 'controller_method') {
                $this->_controller_method = $value;
            }

            $this->_options[$key] = $value;
        }

        // format 4chan archive timestamp
        if ($this->radix->archive) {
            $timestamp = new \DateTime(date('Y-m-d H:i:s', $this->timestamp), new \DateTimeZone('America/New_York'));
            $timestamp->setTimezone(new \DateTimeZone('UTC'));
            $this->timestamp = $timestamp->getTimestamp() + $timestamp->getOffset();

            if ($this->timestamp_expired > 0) {
                $timestamp_expired = new \DateTime(date('Y-m-d H:i:s', $this->timestamp_expired), new \DateTimeZone('America/New_York'));
                $timestamp_expired->setTimezone(new \DateTimeZone('UTC'));
                $this->timestamp_expired = $timestamp_expired->getTimestamp() + $timestamp_expired->getOffset();
            }
        }

        if ($this->_options['clean']) {
            $this->cleanFields();
        }

        if ($this->_options['prefetch_backlinks']) {
            // to get the backlinks we need to get the comment processed
            $this->getCommentProcessed();
        }

        if ($this->poster_country !== null) {
            $this->poster_country_name = $this->config->get('foolz/foolfuuka', 'geoip_codes', 'codes.'.strtoupper($this->poster_country));
        }

        $num = $this->num.($this->subnum ? ','.$this->subnum : '');
        $this->comment_factory->_posts[$this->thread_num][] = $num;
    }

    public function getOriginalTimestamp()
    {
        return $this->timestamp;
    }

    public function getFourchanDate()
    {
        if ($this->fourchan_date === false) {
            $fourtime = new \DateTime('@'.$this->getOriginalTimestamp());
            $fourtime->setTimezone(new \DateTimeZone('America/New_York'));

            $this->fourchan_date = $fourtime->format('n/j/y(D)G:i');
        }

        return $this->fourchan_date;
    }

    public function getCommentSanitized()
    {
        if ($this->comment_sanitized === false) {
            $this->comment_sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $this->comment);
        }

        return $this->comment_sanitized;
    }

    public function getCommentProcessed()
    {
        if ($this->comment_processed === false) {
            $this->comment_processed = @iconv('UTF-8', 'UTF-8//IGNORE', $this->processComment());
        }

        return $this->comment_processed;
    }

    public function getFormatted($params = [])
    {
        if ($this->formatted === false) {
            $this->formatted = $this->buildComment($params);
        }

        return $this->formatted;
    }

    public function getReports()
    {
        if ($this->reports === false) {
            if ($this->getAuth()->hasAccess('comment.reports')) {
                $reports = $this->report_coll->getByDocId($this->radix, $this->doc_id);

                if ($this->media) {
                    $reports += $this->report_coll->getByMediaId($this->radix, $this->media->media_id);
                }

                $this->reports = $reports;
            } else {
                $this->reports = [];
            }
        }

        return $this->reports;

    }

    public static function process($string)
    {
        return htmlentities(@iconv('UTF-8', 'UTF-8//IGNORE', $string));
    }

    public function getTitleProcessed()
    {
        if ($this->title_processed === false) {
            $this->title_processed = static::process($this->title);
        }

        return $this->title_processed;
    }

    public function getNameProcessed()
    {
        if ($this->name_processed === false) {
            $this->name_processed = static::process($this->name);
        }

        return $this->name_processed;
    }

    public function getEmailProcessed()
    {
        if ($this->email_processed === false) {
            $this->email_processed = static::process($this->email);
        }

        return $this->email_processed;
    }

    public function getTripProcessed()
    {
        if ($this->trip_processed === false) {
            $this->trip_processed = static::process($this->trip);
        }

        return $this->trip_processed;
    }

    public function getPosterHashProcessed()
    {
        if ($this->poster_hash_processed === false) {
            $this->poster_hash_processed = static::process($this->poster_hash);
        }

        return $this->poster_hash_processed;
    }

    public function getPosterCountryNameProcessed()
    {
        if ($this->poster_country_name_processed === false) {
            if (!isset($this->poster_country_name)) {
                $this->poster_country_name_processed = null;
            } else {
                $this->poster_country_name_processed = static::process($this->poster_country_name);
            }
        }

        return $this->poster_country_name_processed;
    }

    /**
     * Processes the comment, strips annoying data from moot, converts BBCode,
     * converts > to greentext, >> to internal link, and >>> to external link
     *
     * @param object $board
     * @param object $post the database row for the post
     * @return string the processed comment
     */
    public function processComment()
    {
        // default variables
        $find = "'(\r?\n|^)(&gt;.*?)(?=$|\r?\n)'i";
        $html = '\\1<span class="greentext">\\2</span>\\3';

        $html = Hook::forge('Foolz\Foolfuuka\Model\Comment::processComment.result.greentext')
            ->setParam('html', $html)
            ->execute()
            ->get($html);

        $comment = $this->comment;

        // this stores an array of moot's formatting that must be removed
        $special = [
            '<div style="padding: 5px;margin-left: .5em;border-color: #faa;border: 2px dashed rgba(255,0,0,.1);border-radius: 2px">',
            '<span style="padding: 5px;margin-left: .5em;border-color: #faa;border: 2px dashed rgba(255,0,0,.1);border-radius: 2px">'
        ];

        // remove moot's special formatting
        if ($this->capcode == 'A' && mb_strpos($comment, $special[0]) == 0) {
            $comment = str_replace($special[0], '', $comment);

            if (mb_substr($comment, -6, 6) == '</div>') {
                $comment = mb_substr($comment, 0, mb_strlen($comment) - 6);
            }
        }

        if ($this->capcode == 'A' && mb_strpos($comment, $special[1]) == 0) {
            $comment = str_replace($special[1], '', $comment);

            if (mb_substr($comment, -10, 10) == '[/spoiler]') {
                $comment = mb_substr($comment, 0, mb_strlen($comment) - 10);
            }
        }

        $comment = htmlentities($comment, ENT_COMPAT | ENT_IGNORE, 'UTF-8', false);

        // preg_replace_callback handle
        $this->current_board_for_prc = $this->radix;

        // format entire comment
        $comment = preg_replace_callback("'(&gt;&gt;(\d+(?:,\d+)?))'i",
            [$this, 'processInternalLinks'], $comment);

        $comment = preg_replace_callback("'(&gt;&gt;&gt;(\/(\w+)\/([\w-]+(?:,\d+)?)?(\/?)))'i",
            [$this, 'processExternalLinks'], $comment);

        $comment = preg_replace($find, $html, $comment);
        $comment = static::parseBbcode($comment, ($this->radix->archive && !$this->subnum));
        $comment = static::autoLinkify($comment, 'url', true);

        // additional formatting
        if ($this->radix->archive && !$this->subnum) {
            // admin bbcode
            $admin_find = "'\[banned\](.*?)\[/banned\]'i";
            $admin_html = '<span class="banned">\\1</span>';

            $comment = preg_replace($admin_find, $admin_html, $comment);
            $comment = preg_replace("'\[(/?(banned|moot|spoiler|code)):lit\]'i", "[$1]", $comment);
        }

        $comment = nl2br(trim($comment));

        if (preg_match_all('/\<pre\>(.*?)\<\/pre\>/', $comment, $match)) {
            foreach ($match as $a) {
                foreach ($a as $b) {
                    $comment = str_replace('<pre>'.$b.'</pre>', "<pre>".str_replace("<br />", "", $b)."</pre>", $comment);
                }
            }
        }

        return $this->comment_processed = $comment;
    }

    protected static function parseBbcode($str, $special_code, $strip = true)
    {
        if (static::$_bbcode_parser === null) {
            $bbcode = new \StringParser_BBCode();

            $codes = [];

            // add list of bbcode for formatting
            $codes[] = ['code', 'simple_replace', null, ['start_tag' => '<code>', 'end_tag' => '</code>'], 'code',
                ['block', 'inline'], []];
            $codes[] = ['spoiler', 'simple_replace', null,
                ['start_tag' => '<span class="spoiler">', 'end_tag' => '</span>'], 'inline', ['block', 'inline'],
                ['code']];
            $codes[] = ['sub', 'simple_replace', null, ['start_tag' => '<sub>', 'end_tag' => '</sub>'], 'inline',
                ['block', 'inline'], ['code']];
            $codes[] = ['sup', 'simple_replace', null, ['start_tag' => '<sup>', 'end_tag' => '</sup>'], 'inline',
                ['block', 'inline'], ['code']];
            $codes[] = ['b', 'simple_replace', null, ['start_tag' => '<b>', 'end_tag' => '</b>'], 'inline',
                ['block', 'inline'], ['code']];
            $codes[] = ['i', 'simple_replace', null, ['start_tag' => '<em>', 'end_tag' => '</em>'], 'inline',
                ['block', 'inline'], ['code']];
            $codes[] = ['m', 'simple_replace', null, ['start_tag' => '<tt class="code">', 'end_tag' => '</tt>'],
                'inline', ['block', 'inline'], ['code']];
            $codes[] = ['o', 'simple_replace', null, ['start_tag' => '<span class="overline">', 'end_tag' => '</span>'],
                'inline', ['block', 'inline'], ['code']];
            $codes[] = ['s', 'simple_replace', null,
                ['start_tag' => '<span class="strikethrough">', 'end_tag' => '</span>'], 'inline', ['block', 'inline'],
                ['code']];
            $codes[] = ['u', 'simple_replace', null,
                ['start_tag' => '<span class="underline">', 'end_tag' => '</span>'], 'inline', ['block', 'inline'],
                ['code']];
            $codes[] = ['EXPERT', 'simple_replace', null,
                ['start_tag' => '<span class="expert">', 'end_tag' => '</span>'], 'inline', ['block', 'inline'],
                ['code']];

            foreach($codes as $code) {
                if ($strip) {
                    $code[1] = 'callback_replace';
                    $code[2] = '\\Comment::stripUnusedBbcode'; // this also fixes pre/code
                }

                $bbcode->addCode($code[0], $code[1], $code[2], $code[3], $code[4], $code[5], $code[6]);
            }

            static::$_bbcode_parser = $bbcode;
        }

        // if $special == true, add special bbcode
        if ($special_code === true) {
            /* @todo put this into form bootstrap
            if ($CI->theme->get_selected_theme() == 'fuuka') {
                $bbcode->addCode('moot', 'simple_replace', null,
                    ['start_tag' => '<div style="padding: 5px;margin-left: .5em;border-color: #faa;border: 2px dashed rgba(255,0,0,.1);border-radius: 2px">', 'end_tag' => '</div>'),
                    'inline', array['block', 'inline'], []);
            } else {*/
                static::$_bbcode_parser->addCode('moot', 'simple_replace', null, ['start_tag' => '', 'end_tag' => ''], 'inline',
                    ['block', 'inline'], []);
            /* } */
        }

        return static::$_bbcode_parser->parse($str);
    }

    public static function stripUnusedBbcode($action, $attributes, $content, $params, &$node_object)
    {
        if ($content === '' || $content === false) {
            return '';
        }

        // if <code> has multiple lines, wrap it in <pre> instead
        if ($params['start_tag'] == '<code>') {
            if (count(array_filter(preg_split('/\r\n|\r|\n/', $content))) > 1) {
                return '<pre>'.$content.'</pre>';
            }
        }

        // limit nesting level
        $parent_count = 0;
        $temp_node_object = $node_object;
        while ($temp_node_object->_parent !== null) {
            $parent_count++;
            $temp_node_object = $temp_node_object->_parent;

            if (in_array($params['start_tag'], ['<sub>', '<sup>']) && $parent_count > 1) {
                return $content;
            } elseif ($parent_count > 4) {
                return $content;
            }
        }

        return $params['start_tag'].$content.$params['end_tag'];
    }

    /**
     * A callback function for preg_replace_callback for internal links (>>)
     * Notice: this function generates some class variables
     *
     * @param array $matches the matches sent by preg_replace_callback
     * @return string the complete anchor
     */
    public function processInternalLinks($matches)
    {
        // don't process when $this->num is 0
        if ($this->num == 0) {
            return $matches[0];
        }

        $num = $matches[2];

        // create link object with all relevant information
        $data = new \stdClass();
        $data->num = str_replace(',', '_', $matches[2]);
        $data->board = $this->radix;
        $data->post = $this;

        $current_p_num_c = $this->num.($this->subnum ? ','.$this->subnum : '');
        $current_p_num_u = $this->num.($this->subnum ? '_'.$this->subnum : '');

        $build_url = [
            'tags' => ['', ''],
            'hash' => '',
            'attr' => 'class="backlink" data-function="highlight" data-backlink="true" data-board="' . $data->board->shortname . '" data-post="' . $data->num . '"',
            'attr_op' => 'class="backlink op" data-function="highlight" data-backlink="true" data-board="' . $data->board->shortname . '" data-post="' . $data->num . '"',
            'attr_backlink' => 'class="backlink" data-function="highlight" data-backlink="true" data-board="' . $data->board->shortname . '" data-post="' . $current_p_num_u . '"',
        ];

        $build_url = Hook::forge('Foolz\Foolfuuka\Model\Comment::processInternalLinks.result.html')
            ->setObject($this)
            ->setParam('data', $data)
            ->setParam('build_url', $build_url)
            ->execute()
            ->get($build_url);

        $this->comment_factory->_backlinks_arr[$data->num][$current_p_num_u] = implode(
            '<a href="' . $this->uri->create([$data->board->shortname, $this->_controller_method, $data->post->thread_num]) . '#' . $build_url['hash'] . $current_p_num_u . '" ' .
            $build_url['attr_backlink'] . '>&gt;&gt;' . $current_p_num_c . '</a>'
        , $build_url['tags']);

        if (array_key_exists($num, $this->comment_factory->_posts)) {
            if ($this->_backlinks_hash_only_url) {
                return implode('<a href="#' . $build_url['hash'] . $data->num . '" '
                    . $build_url['attr_op'] . '>&gt;&gt;' . $num . '</a>', $build_url['tags']);
            }

            return implode('<a href="' . $this->uri->create([$data->board->shortname, $this->_controller_method, $num]) . '#' . $data->num . '" '
                . $build_url['attr_op'] . '>&gt;&gt;' . $num . '</a>', $build_url['tags']);
        }

        foreach ($this->comment_factory->_posts as $key => $thread) {
            if (in_array($num, $thread)) {
                if ($this->_backlinks_hash_only_url) {
                    return implode('<a href="#' . $build_url['hash'] . $data->num . '" '
                        . $build_url['attr'] . '>&gt;&gt;' . $num . '</a>', $build_url['tags']);
                }

                return implode('<a href="' . $this->uri->create([$data->board->shortname, $this->_controller_method, $key]) . '#' . $build_url['hash'] . $data->num . '" '
                    . $build_url['attr'] . '>&gt;&gt;' . $num . '</a>', $build_url['tags']);
            }
        }

        return implode('<a href="' . $this->uri->create([$data->board->shortname, 'post', $data->num]) . '" '
            . $build_url['attr'] . '>&gt;&gt;' . $num . '</a>', $build_url['tags']);

        // return un-altered
        return $matches[0];
    }

    public function getBacklinks()
    {
        $num = $this->subnum ? $this->num.'_'.$this->subnum : $this->num;

        if (isset($this->comment_factory->_backlinks_arr[$num])) {
            ksort($this->comment_factory->_backlinks_arr[$num], SORT_STRING);
            return $this->comment_factory->_backlinks_arr[$num];
        }

        return [];
    }

    /**
     * A callback function for preg_replace_callback for external links (>>>//)
     * Notice: this function generates some class variables
     *
     * @param array $matches the matches sent by preg_replace_callback
     * @return string the complete anchor
     */
    public function processExternalLinks($matches)
    {
        // create $data object with all results from $matches
        $data = new \stdClass();
        $data->link = $matches[2];
        $data->shortname = $matches[3];
        $data->board = $this->radix_coll->getByShortname($data->shortname);
        $data->query = $matches[4];

        $build_href = [
            // this will wrap the <a> element with a container element [open, close]
            'tags' => ['open' => '', 'close' => ''],

            // external links; defaults to 4chan
            'short_link' => '//boards.4chan.org/'.$data->shortname.'/',
            'query_link' => '//boards.4chan.org/'.$data->shortname.'/res/'.$data->query,

            // additional attributes + backlinking attributes
            'attributes' => '',
            'backlink_attr' => ' class="backlink" data-function="highlight" data-backlink="true" data-board="'
                .(($data->board)?$data->board->shortname:$data->shortname).'" data-post="'.$data->query.'"'
        ];

        $build_href = Hook::forge('Foolz\Foolfuuka\Model\Comment::processExternalLinks.result.html')
            ->setObject($this)
            ->setParam('data', $data)
            ->setParam('build_href', $build_href)
            ->execute()
            ->get($build_href);

        if (!$data->board) {
            if ($data->query) {
                return implode('<a href="'.$build_href['query_link'].'"'.$build_href['attributes'].'>&gt;&gt;&gt;'.$data->link.'</a>', $build_href['tags']);
            }

            return implode('<a href="'.$build_href['short_link'].'">&gt;&gt;&gt;'.$data->link.'</a>', $build_href['tags']);
        }

        if ($data->query) {
            return implode('<a href="'.$this->uri->create([$data->board->shortname, 'post', $data->query]).'"'
                .$build_href['attributes'].$build_href['backlink_attr'].'>&gt;&gt;&gt;'.$data->link.'</a>', $build_href['tags']);
        }

        return implode('<a href="' . $this->uri->create($data->board->shortname) . '">&gt;&gt;&gt;' . $data->link . '</a>', $build_href['tags']);
    }

    /**
     * Returns the HTML for the post with the currently selected theme
     *
     * @param object $board
     * @param object $post database row for the post
     * @return string the post box HTML with the selected theme
     */
    public function buildComment($params = [])
    {
        /** @var Builder $builder */
        $builder = $this->_theme;
        $partial = $builder->createPartial('board_comment', 'board_comment');
        $partial->getParamManager()
            ->setParam('p', $this)
            ->setParams($params);

        return $partial->build();
    }

    /**
     * Returns a string with all text links transformed into clickable links
     *
     * @param string $str
     * @param string $type
     * @param boolean $popup
     *
     * @return string
     */
    public static function autoLinkify($str, $type = 'both', $popup = false)
    {
        if ($type != 'email') {
            $target = ($popup == true) ? ' target="_blank"' : '';

            $str = preg_replace("#((^|\s|\(|\])(((http(s?)://)|(www\.))(\w+[^\s\)\<]+)))#i", '$2<a href="$3"'.$target.'>$3</a>', $str);
        }

        return $str;
    }

    /**
     * Returns the timestamp fixed for the radix time
     *
     * @param int|null $time If a timestamp is supplied, it will calculate the time in relation to that moment
     *
     * @return int resulting timestamp
     */
    public function getRadixTime($time = null)
    {
        if ($time === null) {
            $time = time();
        }

        if ($this->radix->archive) {
            $datetime = new \DateTime(date('Y-m-d H:i:s', $time), new \DateTimeZone('UTC'));
            $datetime->setTimezone(new \DateTimeZone('America/New_York'));

            return $datetime->getTimestamp() + $datetime->getOffset();
        } else {
            return $time;
        }
    }

    public function cleanFields()
    {
        Hook::forge('Foolz\Foolfuuka\Model\Comment::cleanFields.call.before.body')
            ->setObject($this)
            ->execute();

        if (!$this->getAuth()->hasAccess('comment.see_ip')) {
            unset($this->poster_ip);
        }

        unset($this->delpass);
    }

    /**
     * Delete the post and eventually the entire thread if it's OP
     * Also deletes the images when it's the only post with that image
     *
     * @return array|bool
     */
    protected function p_delete($password = null, $force = false, $thread = false)
    {
        if (!$this->getAuth()->hasAccess('comment.passwordless_deletion') && $force !== true) {
            if (!class_exists('PHPSecLib\\Crypt_Hash', false)) {
                import('phpseclib/Crypt/Hash', 'vendor');
            }

            $hasher = new \PHPSecLib\Crypt_Hash();

            $hashed = base64_encode($hasher->pbkdf2($password, $this->config->get('foolz/foolframe', 'foolauth', 'salt'), 10000, 32));

            if ($this->delpass !== $hashed) {
                throw new CommentDeleteWrongPassException(_i('You did not provide the correct deletion password.'));
            }
        }

        try {
            $this->dc->getConnection()->beginTransaction();

            // throw into _deleted table
            $this->dc->getConnection()->executeUpdate(
                'INSERT INTO '.$this->radix->getTable('_deleted').' '.
                    $this->dc->qb()
                        ->select('*')
                        ->from($this->radix->getTable(), 't')
                        ->where('doc_id = '.$this->dc->getConnection()->quote($this->doc_id))
                        ->getSQL()
            );

            // delete post
            $this->dc->qb()
                ->delete($this->radix->getTable())
                ->where('doc_id = :doc_id')
                ->setParameter(':doc_id', $this->doc_id)
                ->execute();

            // remove any extra data
            $this->dc->qb()
                ->delete($this->radix->getTable('_extra'))
                ->where('extra_id = :doc_id')
                ->setParameter(':doc_id', $this->doc_id)
                ->execute();

            // purge reports
            $this->dc->qb()
                ->delete($this->dc->p('reports'))
                ->where('board_id = :board_id')
                ->andWhere('doc_id = :doc_id')
                ->setParameter(':board_id', $this->radix->id)
                ->setParameter(':doc_id', $this->doc_id)
                ->execute();

            // clear cache
            $this->radix_coll->clearCache();

            // remove image file
            if (isset($this->media)) {
                $this->media->delete();
            }

            // if this is OP, delete replies too
            if ($this->op) {
                // delete thread data
                $this->dc->qb()
                    ->delete($this->radix->getTable('_threads'))
                    ->where('thread_num = :thread_num')
                    ->setParameter(':thread_num', $this->thread_num)
                    ->execute();

                // process each comment
                $comments = $this->dc->qb()
                    ->select('doc_id')
                    ->from($this->radix->getTable(), 'b')
                    ->where('thread_num = :thread_num')
                    ->setParameter(':thread_num', $this->thread_num)
                    ->execute()
                    ->fetchAll();

                foreach ($comments as $comment) {
                    $post = Board::forge($this->getContext())
                        ->getPost()
                        ->setOptions('doc_id', $comment['doc_id'])
                        ->setRadix($this->radix)
                        ->getComments();

                    $post = current($post);
                    $post->delete(null, true, true);
                }
            } else {
                // if this is not triggered by a thread deletion, update the thread table
                if ($thread === false && !$this->radix->archive) {
                    $time_last = '
                    (
                        COALESCE(GREATEST(
                            time_op,
                            (
                                SELECT MAX(timestamp) FROM '.$this->radix->getTable().' xr
                                WHERE thread_num = '.$this->dc->getConnection()->quote($this->thread_num).' AND subnum = 0
                            )
                        ), time_op)
                    )';

                    $time_bump = '
                    (
                        COALESCE(GREATEST(
                            time_op,
                            (
                                SELECT MAX(timestamp) FROM '.$this->radix->getTable().' xr
                                WHERE thread_num = '.$this->dc->getConnection()->quote($this->thread_num).' AND subnum = 0
                                    AND (email <> \'sage\' OR email IS NULL)
                            )
                        ), time_op)
                    )';

                    $time_ghost = '
                    (
                        SELECT MAX(timestamp) FROM '.$this->radix->getTable().' xr
                        WHERE thread_num = '.$this->dc->getConnection()->quote($this->thread_num).' AND subnum <> 0
                    )';

                    $time_ghost_bump = '
                    (
                        SELECT MAX(timestamp) FROM '.$this->radix->getTable().' xr
                        WHERE thread_num = '.$this->dc->getConnection()->quote($this->thread_num).' AND subnum <> 0
                            AND (email <> \'sage\' OR email IS NULL)
                    )';

                    // update thread information
                    $this->dc->qb()
                        ->update($this->radix->getTable('_threads'))
                        ->set('time_last', $time_last)
                        ->set('time_bump', $time_bump)
                        ->set('time_ghost', $time_ghost)
                        ->set('time_ghost_bump', $time_ghost_bump)
                        ->set('time_last_modified', ':time')
                        ->set('nreplies', 'nreplies - 1')
                        ->set('nimages', (isset($this->media) ? 'nimages - 1' : 'nimages'))
                        ->where('thread_num = :thread_num')
                        ->setParameter(':time', $this->getRadixTime())
                        ->setParameter(':thread_num', $this->thread_num)
                        ->execute();
                }
            }

            $this->dc->getConnection()->commit();

            // clean up some caches
            Cache::item('foolfuuka.model.board.getThreadComments.thread.'
                .md5(serialize([$this->radix->shortname, $this->thread_num])))->delete();

            // clean up the 10 first pages of index and gallery that are cached
            for ($i = 1; $i <= 10; $i++) {
                Cache::item('foolfuuka.model.board.getLatestComments.query.'
                    .$this->radix->shortname.'.by_post.'.$i)->delete();

                Cache::item('foolfuuka.model.board.getLatestComments.query.'
                    .$this->radix->shortname.'.by_thread.'.$i)->delete();

                Cache::item('foolfuuka.model.board.getThreadsComments.query.'
                    .$this->radix->shortname.'.'.$i)->delete();
            }
        } catch (\Doctrine\DBAL\DBALException $e) {
            $this->logger->error('\Foolz\Foolfuuka\Model\CommentInsert: '.$e->getMessage());
            $this->dc->getConnection()->rollBack();

            throw new CommentSendingDatabaseException(_i('Something went wrong when deleting the post in the database. Try again.'));
        }

        return $this;
    }

    /**
     * Processes the name with unprocessed tripcode and returns name and processed tripcode
     *
     * @return array name without tripcode and processed tripcode concatenated with processed secure tripcode
     */
    protected function p_processName()
    {
        $name = $this->name;

        // define variables
        $matches = [];
        $normal_trip = '';
        $secure_trip = '';

        if (preg_match("'^(.*?)(#)(.*)$'", $this->name, $matches)) {
            $matches_trip = [];
            $name = trim($matches[1]);

            preg_match("'^(.*?)(?:#+(.*))?$'", $matches[3], $matches_trip);

            if (count($matches_trip) > 1) {
                $normal_trip = $this->processTripcode($matches_trip[1]);
                $normal_trip = $normal_trip ? '!'.$normal_trip : '';
            }

            if (count($matches_trip) > 2) {
                $secure_trip = '!!'.$this->processSecureTripcode($matches_trip[2]);
            }
        }

        $this->name = $name;
        $this->trip = $normal_trip . $secure_trip;

        return ['name' => $name, 'trip' => $normal_trip . $secure_trip];
    }

    /**
     * Processes the tripcode
     *
     * @param string $plain the word to generate the tripcode from
     * @return string the processed tripcode
     */
    protected function p_processTripcode($plain)
    {
        if (trim($plain) == '') {
            return '';
        }

        $trip = mb_convert_encoding($plain, 'SJIS', 'UTF-8');

        $salt = substr($trip.'H.', 1, 2);
        $salt = preg_replace('/[^.-z]/', '.', $salt);
        $salt = strtr($salt, ':;<=>?@[\]^_`', 'ABCDEFGabcdef');

        return substr(crypt($trip, $salt), -10);
    }

    /**
     * Process the secure tripcode
     *
     * @param string $plain the word to generate the secure tripcode from
     * @return string the processed secure tripcode
     */
    protected function p_processSecureTripcode($plain)
    {
        return substr(base64_encode(sha1($plain . base64_decode($this->config->get('foolz/foolfuuka', 'config', 'comment.secure_tripcode_salt')), true)), 0, 11);
    }
}
