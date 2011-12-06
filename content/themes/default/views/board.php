<?php
if (!defined('BASEPATH'))
	exit('No direct script access allowed');

foreach ($posts as $key => $post) :
	?>

	<article <?php if (isset($post['op'])) : ?>id="<?php echo $post['op']->num ?>" class="thread doc_id_<?php echo $post['op']->doc_id ?>"<?php else: ?> class="thread" <?php endif; ?>>
		<div class="thread_divider"></div>
		<?php
		if (isset($post['op'])) :
			$op = $post['op'];
			?>
			<?php if ($op->media_filename) : ?>
				<div class="thread_image_box">
					<a href="<?php echo $op->remote_image_href ?>" target="_blank" class="thread_image_link"><img src="<?php echo $op->thumbnail_href ?>" width="<?php echo $op->preview_w ?>" height="<?php echo $op->preview_h ?>" md5="<?php echo $op->media_hash ?>" class="thread_image" /></a>
					<div class="post_file" style="padding-left: 2px"><?php echo byte_format($op->media_size, 0) . ', ' . $op->media_w . 'x' . $op->media_h . ', ' . $op->media ?></div>
					<div class="post_file_controls">
						<a href="<?php echo site_url($this->fu_board . '/image/' . urlencode(substr($op->media_hash, 0, -2))) ?>" class="btn parent">View Same</a><a target="_blank" href="http://iqdb.org/?url=<?php echo $op->thumbnail_href ?>" class="btn parent">iqdb</a><a target="_blank" href="http://google.com/searchbyimage?image_url=<?php echo $op->thumbnail_href ?>" class="btn parent">Google</a>
					</div>
				</div>
			<?php endif; ?>

			<header class="<?php echo ((isset($op->report_status) && !is_null($op->report_status)) ? ' reported' : '') ?>">
				<div class="post_data">
					<div class="input-prepend">
							<h2 class="post_title"><?php echo $op->title_processed ?></h2>
							<span class="post_author"><?php echo (($op->email_processed) ? '<a href="mailto:' . form_prep($op->email_processed) . '">' . $op->name_processed . '</a>' : $op->name_processed) ?></span>
							<span class="post_trip"><?php echo $op->trip_processed ?></span>
							<?php if ($op->capcode == 'M') : ?>
								<span class="post_level post_level_moderator">## Mod</span>
							<?php endif ?>
							<?php if ($op->capcode == 'G') : ?>
								<span class="post_level post_level_global_moderator">## Global Mod</span>
							<?php endif ?>
							<?php if ($op->capcode == 'A') : ?>
								<span class="post_level post_level_administrator">## Admin</span>
							<?php endif ?>
							<time datetime="<?php echo date(DATE_W3C, $op->timestamp) ?>"><?php echo date('D M d H:i:s Y', $op->timestamp) ?></time>
							<span class="post_number"><a href="<?php echo site_url($this->fu_board . '/thread/' . $op->num) . '#' . $op->num ?>" rel="highlight" id="<?php echo $op->num ?>">No.</a><a href="<?php echo site_url($this->fu_board . '/thread/' . $op->num) . '#q' . $op->num ?>" rel="quote" id="<?php echo $op->num ?>"><?php echo $op->num ?></a></span>
							<span class="post_controls"><a href="<?php echo site_url($this->fu_board . '/thread/' . $op->num) ?>" class="btn parent">View</a><a href="<?php echo site_url($this->fu_board . '/thread/' . $op->num) . '#reply' ?>" class="btn parent">Reply</a><a href="http://boards.4chan.org/<?php echo $this->fu_board . '/res/' . $op->num ?>" class="btn parent">Original</a><a href="<?php echo site_url($this->fu_board . '/report/' . $op->doc_id) ?>" class="btn parent" rel="report" id="<?php echo $op->doc_id ?>" alt="<?php echo $op->num ?>" data-controls-modal="post_tools_report" data-backdrop="true" data-keyboard="true">Report</a><a href="<?php echo site_url($this->fu_board . '/delete/' . $op->doc_id) ?>" class="btn parent" rel="delete" id="<?php echo $op->doc_id ?>" alt="<?php echo $op->num ?>" data-controls-modal="post_tools_delete" data-backdrop="true" data-keyboard="true">Delete</a></span>
					</div>
				</div>
			</header>
			<div class="text">
				<?php echo $op->comment_processed ?>
				<?php echo ((isset($post['omitted']) && $post['omitted'] > 0) ? '<h6>' . $post['omitted'] . ' posts omitted.</h6>' : '') ?>
			</div>
			<div style="clear:both"></div>
		<?php endif; ?>
		<?php if (isset($post['posts'])) : ?>
			<aside class="posts">

				<?php
				if (isset($posts_per_thread))
				{
					$limit = count($post['posts']) - $posts_per_thread;
					if ($limit < 0)
						$limit = 0;
				}
				else
				{
					$limit = 0;
				}

				for ($i = $limit; $i < count($post['posts']); $i++) :
					$p = $post['posts'][$i];

					if ($p->parent == 0)
						$p->parent = $p->num;
					?>

					<article class="post doc_id_<?php echo $p->doc_id ?><?php if ($p->subnum > 0) : ?> post_ghost<?php endif; ?><?php echo ((isset($p->report_status) && !is_null($p->report_status)) ? ' reported' : '') ?>" id="<?php echo $p->num . (($p->subnum > 0) ? '_' . $p->subnum : '') ?>">
						<?php if ($p->media_filename) : ?>
							<div class="thread_image_box">
								<div class="post_file"><?php echo $p->media ?></div>
								<a href="<?php echo $p->remote_image_href ?>" target="_blank" class="thread_image_link"><img src="<?php echo $p->thumbnail_href ?>" width="<?php echo $p->preview_w ?>" height="<?php echo $p->preview_h ?>" md5="<?php echo $p->media_hash ?>" class="post_image" /></a>
								<div class="post_file"><?php echo byte_format($p->media_size, 0) . ', ' . $p->media_w . 'x' . $p->media_h ?></div>
								<div class="post_file_controls">
									<a href="<?php echo site_url($this->fu_board . '/image/' . urlencode(substr($p->media_hash, 0, -2))) ?>" class="btn parent">View Same</a><a target="_blank" href="http://iqdb.org/?url=<?php echo $p->thumbnail_href ?>" class="btn parent">iqdb</a><a target="_blank" href="http://google.com/searchbyimage?image_url=<?php echo $p->thumbnail_href ?>" class="btn parent">Google</a>
								</div>
							</div>
						<?php endif; ?>
						<header>
							<div class="post_data">
								<h2 class="post_title"><?php echo $p->title_processed ?></h2>
								<span class="post_author"><?php echo (($p->email_processed) ? '<a href="mailto:' . form_prep($p->email_processed) . '">' . $p->name_processed . '</a>' : $p->name_processed) ?></span> <span class="post_trip"><?php echo $p->trip_processed ?></span>
								<?php if ($p->capcode == 'M') : ?>
									<span class="post_level post_level_moderator">## Mod</span>
								<?php endif ?>
								<?php if ($p->capcode == 'G') : ?>
									<span class="post_level post_level_global_moderator">## Global Mod</span>
								<?php endif ?>
								<?php if ($p->capcode == 'A') : ?>
									<span class="post_level post_level_administrator">## Admin</span>
								<?php endif ?>
								<time datetime="<?php echo date(DATE_W3C, $p->timestamp) ?>"><?php echo date('D M d H:i:s Y', $p->timestamp) ?></time>
								<span class="post_number"><a href="<?php echo site_url($this->fu_board . '/thread/' . $p->parent) . '#' . $p->num . (($p->subnum > 0) ? '_' . $p->subnum : '') ?>" rel="highlight" id="<?php echo $p->num . (($p->subnum > 0) ? '_' . $p->subnum : '') ?>">No.</a><a href="<?php echo site_url($this->fu_board . '/thread/' . $p->parent) . '#q' . $p->num . (($p->subnum > 0) ? '_' . $p->subnum : '') ?>" rel="quote" id="<?php echo $p->num . (($p->subnum > 0) ? ',' . $p->subnum : '') ?>"><?php echo $p->num . (($p->subnum > 0) ? ',' . $p->subnum : '') ?></a></span>
								<?php if (isset($thread_id)) : ?>
									<span class="post_controls">
										<a href="<?php echo site_url($this->fu_board . '/report/' . $p->doc_id) ?>" class="btn parent" rel="report" id="<?php echo $p->doc_id ?>" alt="<?php echo $p->num . (($p->subnum > 0) ? ',' . $p->subnum : '') ?>" data-controls-modal="post_tools_report" data-backdrop="true" data-keyboard="true">Report</a><a href="<?php echo site_url($this->fu_board . '/delete/' . $p->doc_id) ?>" class="btn parent" rel="delete" id="<?php echo $p->doc_id ?>" alt="<?php echo $p->num . (($p->subnum > 0) ? ',' . $p->subnum : '') ?>" data-controls-modal="post_tools_delete" data-backdrop="true" data-keyboard="true">Delete</a>
									</span>
								<?php endif; ?>
							</div>
							<?php if ($p->subnum > 0) : ?><span class="post_type"><img src="<?php echo icons(356, 16) ?>" title="This is a ghost post, not coming from 4chan"/></span><?php endif ?>
						</header>
						<div class="text">
							<?php echo $p->comment_processed ?>
						</div>
					<div style="clear:both"></div>
					</article>
				<?php endfor; ?>
			</aside>
		<?php endif; ?>
		<div class="clearfix"></div>
		<?php echo $template['partials']['post_reply']; ?>
	</article>
<?php endforeach; ?>
