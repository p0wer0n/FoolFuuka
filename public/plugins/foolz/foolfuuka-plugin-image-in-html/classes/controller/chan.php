<?php

if (!defined('DOCROOT'))
    exit('No direct script access allowed');

namespace Foolfuuka\Plugins\ImageInHtml;

class ControllerPluginFuImageInHtmlChan extends \Foolfuuka\Controller_Chan
{

    public function radixImageHtml($filename)
    {
        // Check if $filename is valid.
        if (!in_array(\Input::extension(), ['gif', 'jpg', 'png']) || !ctype_digit(substr($filename, 0, 13))) {
            return $this->action_404(_i('The filename submitted is not compatible with the system.'));
        }

        try {
            $media = \Media::get_by_filename($this->_radix, $filename.'.'.\Input::extension());
        } catch (\Foolz\Foolfuuka\Model\MediaException $e) {
            return $this->action_404(_i('The image was not found.'));
        }

        if ($media->media_link !== null) {
            ob_start();
            ?>
            <article class="full_image">
                <a href="<?= $media->getLink(false, true) ?>"><img src="<?= $media->getLink(false, true) ?>"></a>
                <nav>
                    <?php if ($media->total) : ?><a href="<?= \Uri::create($this->_radix->shortname.'/search/image/'.$media->safe_media_hash) ?>" class="btnr parent"><?= _i('View Same') ?></a><?php endif; ?>
                    <a href="http://google.com/searchbyimage?image_url=<?= $media->thumb_link ?>" target="_blank" class="btnr parent">Google</a>
                    <a href="http://iqdb.org/?url=<?= $media->thumb_link ?>" target="_blank" class="btnr parent">iqdb</a>
                    <a href="http://saucenao.ci'd om/search.php?url=<?= $media->thumb_link ?>" target="_blank" class="btnr parent">SauceNAO</a>
                    <a href="#" class="btnr parent" style="background-color: #EF8B77; color: #fff" data-media-id="<?= $media->media_id ?>" data-board="<?= htmlspecialchars($this->_radix->shortname) ?>" data-controls-modal="post_tools_modal" data-backdrop="true" data-keyboard="true" data-function="reportMedia"><?= _i('Report') ?></a>
                </nav>
            </article>
            <style>
                .theme_default .full_image {
                    margin: 10px auto;
                    padding: 4px;
                    text-align:center
                }

                .theme_default .full_image nav {
                    margin: 10px
                }

                .theme_default .full_image nav .btnr {
                    font: 20px
                }

                .theme_default .full_image img {
                    max-width: 90%
                }

                .theme_default article.thread article.post:nth-of-type(-n+4) {
                    display:block;
                    float:none;
                    margin: 0 auto
                }

                .theme_default .post {
                    width: 50%;
                    display:block;
                    float:none;
                    margin: 10px auto
                }
            </style>
            <?php
            $content = ob_get_clean();

            $this->_theme->bind('section_title', _i('Displaying image %s', $media->media));

            try {
                $board = \Search::forge()
                    ->get_search(['image' => $media->media_hash])
                    ->set_radix($this->_radix)
                    ->set_options('limit', 5)
                    ->set_page(1);

                $board->get_comments();
            } catch (\Foolz\Foolfuuka\Model\SearchException $e) {
                $board_html = '';
            } catch (\Foolz\Foolfuuka\Model\SearchEmptyResultException $e) {
                $board_html = '';
            } catch (\Foolz\Foolfuuka\Model\BoardException $e) {
                $board_html = '';
            }

            $image_html = $this->_theme->build('plugin', ['content' => $content], true);

            // we got search results
            if (!isset($board_html)) {
                $board_html = $this->_theme->build('board', ['board' => $board, 'disable_default_after_headless_open' => true], true);
            }

            return \Response::forge($this->_theme->build('plugin', ['content' => $image_html.$board_html]));
        }

        return \Response::redirect(
            \Uri::create([$this->_radix->shortname, 'search', 'image', rawurlencode(substr($media->media_hash, 0, -2))]), 'location', 404);
    }
}
