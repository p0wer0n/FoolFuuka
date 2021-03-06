<?php

namespace Foolz\Foolfuuka\Theme\Foolfuuka\Partial;

use Foolz\Inet\Inet;
use Foolz\Foolframe\Model\Legacy\Preferences;

class Gallery extends \Foolz\Foolfuuka\View\View
{
    public function toString()
    {
        $board = $this->getParamManager()->getParam('board');
        $radix = $this->getBuilderParamManager()->getParam('radix');

        ?>
        <div id="thread_o_matic" class="clearfix">
        <?php
        $separator = 0;
        foreach ($board->getComments() as $k => $p) :
            $separator++;
            ?>
        <article id="<?= $p->num ?>" class="thread doc_id_<?= $p->doc_id ?>">
            <header>
                <div class="post_data">
                    <h2 class="post_title"><?= $p->getTitleProcessed() ?></h2>
                    <span class="post_author"><?= (($p->email && $p->email !== 'noko') ? '<a href="mailto:' . rawurlencode($p->email) . '">' . $p->getNameProcessed() . '</a>' : $p->getNameProcessed()) ?></span>
                    <span class="post_trip"><?= $p->getTripProcessed() ?></span>
                    <span class="poster_hash"><?= ($p->getPosterHashProcessed()) ? 'ID:' . $p->getPosterHashProcessed() : '' ?></span>
                    <?php if ($p->capcode == 'M') : ?>
                    <span class="post_level post_level_moderator">## <?= _i('Mod') ?></span>
                    <?php endif ?>
                    <?php if ($p->capcode == 'A') : ?>
                    <span class="post_level post_level_administrator">## <?= _i('Admin') ?></span>
                    <?php endif ?>
                    <?php if ($p->capcode == 'D') : ?>
                    <span class="post_level post_level_developer">## <?= _i('Developer') ?></span>
                    <?php endif ?><br/>
                    <time datetime="<?= gmdate(DATE_W3C, $p->timestamp) ?>"><?= gmdate('D M d H:i:s Y', $p->timestamp) ?></time>
                    <span class="post_number"><a href="<?= $this->getUri()->create($radix->shortname . '/thread/' . $p->num) . '#' . $p->num ?>" data-function="highlight" data-post="<?= $p->num ?>">No.</a><a href="<?= $this->getUri()->create($radix->shortname . '/thread/' . $p->num) . '#q' . $p->num ?>" data-function="quote" data-post="<?= $p->num ?>"><?= $p->num ?></a></span>
                    <?php if ($p->poster_country !== null) : ?><span class="post_type"><span title="<?= e($p->poster_country_name) ?>" class="flag flag-<?= strtolower($p->poster_country) ?>"></span></span><?php endif; ?>
                    <span class="post_controls"><a href="<?= $this->getUri()->create($radix->shortname . '/thread/' . $p->num) ?>" class="btnr parent"><?= _i('View') ?></a><a href="<?= $this->getUri()->create($radix->shortname . '/thread/' . $p->num) . '#reply' ?>" class="btnr parent"><?= _i('Reply') ?></a><?= (isset($p->count_all) && $p->count_all > 50) ? '<a href="' . $this->getUri()->create($radix->shortname . '/last50/' . $p->num) . '" class="btnr parent">' . _i('Last 50') . '</a>' : '' ?><?php if ($radix->archive == 1) : ?><a href="http://boards.4chan.org/<?= $radix->shortname . '/res/' . $p->num ?>" class="btnr parent"><?= _i('Original') ?></a><?php endif; ?><a href="<?= $this->getUri()->create($radix->shortname . '/report/' . $p->doc_id) ?>" class="btnr parent" data-function="report" data-post="<?= $p->doc_id ?>" data-post-id="<?= $p->num ?>" data-board="<?= htmlspecialchars($p->radix->shortname) ?>" data-controls-modal="post_tools_modal" data-backdrop="true" data-keyboard="true"><?= _i('Report') ?></a><?php if ($this->getAuth()->hasAccess('maccess.mod')) : ?><a href="<?= $this->getUri()->create($radix->shortname . '/delete/' . $p->doc_id) ?>" class="btnr parent" data-function="delete" data-post="<?= $p->doc_id ?>" data-post-id="<?= $p->num ?>" data-board="<?= htmlspecialchars($p->radix->shortname) ?>" data-controls-modal="post_tools_modal" data-backdrop="true" data-keyboard="true"><?= _i('Delete') ?></a><?php endif; ?></span>
                </div>
            </header>
            <?php if ($p->media !== null) : ?>
            <div class="thread_image_box" title="<?= $p->getCommentProcessed() ? htmlspecialchars(strip_tags($p->getCommentProcessed())) : '' ?>">
                <?php if ($p->media->getMediaStatus($this->getRequest()) === 'banned') : ?>
                <img src="<?= $this->getAssetManager()->getAssetLink('images/banned-image.png') ?>" width="150" height="150" />
                <?php elseif ($p->media->getMediaStatus($this->getRequest()) !== 'normal') : ?>
                <a href="<?= ($p->media->getMediaLink($this->getRequest())) ? $p->media->getMediaLink($this->getRequest()) : $p->media->getRemoteMediaLink($this->getRequest()) ?>" target="_blank" rel="noreferrer" class="thread_image_link">
                    <img src="<?= $this->getAssetManager()->getAssetLink('images/missing-image.jpg') ?>" width="150" height="150" />
                </a>
                <?php else: ?>
                <a href="<?= $this->getUri()->create($radix->shortname . '/thread/' . $p->num) ?>" rel="noreferrer" target="_blank" class="thread_image_link"<?= ($p->media->getMediaLink($this->getRequest()))?' data-expand="true"':'' ?>>
                    <?php if (!$this->getAuth()->hasAccess('maccess.mod') && !$radix->getValue('transparent_spoiler') && $p->media->spoiler) :?>
                    <div class="spoiler_box"><span class="spoiler_box_text"><?= _i('Spoiler') ?><span class="spoiler_box_text_help"><?= _i('Click to view') ?></span></div>
                    <?php else : ?>
                    <img src="<?= $p->media->getThumbLink($this->getRequest()) ?>" width="<?= $p->media->preview_w ?>" height="<?= $p->media->preview_h ?>" data-width="<?= $p->media->media_w ?>" data-height="<?= $p->media->media_h ?>" data-md5="<?= $p->media->media_hash ?>" class="thread_image<?= ($p->media->spoiler)?' is_spoiler_image':'' ?>" />
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <?php if ($p->media->getMediaStatus($this->getRequest()) !== 'banned'  || $this->getAuth()->hasAccess('media.see_banned')) : ?>
                <div class="post_file" style="padding-left: 2px"><?= \Num::format_bytes($p->media->media_size, 0) . ', ' . $p->media->media_w . 'x' . $p->media->media_h . ', ' . $p->media->media_filename ?></div>
                <div class="post_file_controls">
                    <a href="<?= ($p->media->getMediaLink($this->getRequest())) ? $p->media->getMediaLink($this->getRequest()) : $p->media->getRemoteMediaLink($this->getRequest()) ?>" class="btnr" target="_blank">Full</a><?php if ($p->media->total > 1) : ?><a href="<?= $this->getUri()->create($radix->shortname . '/search/image/' . urlencode(substr($p->media->media_hash, 0, -2))) ?>" class="btnr parent"><?= _i('View Same') ?></a><?php endif; ?><a target="_blank" href="http://iqdb.org/?url=<?= $p->media->getThumbLink($this->getRequest()) ?>" class="btnr parent">iqdb</a><a target="_blank" href="http://saucenao.com/search.php?url=<?= $p->media->getThumbLink($this->getRequest()) ?>" class="btnr parent">SauceNAO</a><a target="_blank" href="http://google.com/searchbyimage?image_url=<?= $p->media->getThumbLink($this->getRequest()) ?>" class="btnr parent">Google</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="thread_tools_bottom">
                <?php if (isset($p->nreplies)) : ?>
                <?= _i('Replies') ?> : <?= ($p->nreplies - 1) ?> | <?= _i('Images') ?>: <?= ($p->nimages - 1) ?>
                <?php endif; ?>
                <?php if ($p->deleted == 1) : ?><span class="post_type"><img src="<?= $this->getAssetManager()->getAssetLink('images/icons/file-delete-icon.png'); ?>" title="<?= htmlspecialchars(_i('This post was deleted from 4chan manually')) ?>"/></span><?php endif ?>
                <?php if (isset($p->media) && $p->media->spoiler == 1) : ?><span class="post_type"><img src="<?= $this->getAssetManager()->getAssetLink('images/icons/spoiler-icon.png'); ?>" title="<?= htmlspecialchars(_i('This post contains a spoiler image')) ?>"/></span><?php endif ?>
            </div>
        </article>
        <?php
            if ($separator % 4 == 0)
                echo '<div class="clearfix"></div>';
        endforeach;
        ?>
        </div>
        <article class="thread">
            <div id="backlink" class="thread_o_matic" style="position: absolute; top: 0; left: 0; z-index: 5;"></div>
        </article>
        <?php
    }
}
