<?php if (!defined ('ABSPATH')) die ('No direct access allowed'); global $authordata; ?><div class="category-posts">
	<h3>Recent Posts</h3>
	<div>
		<ul>
			<?php 
			// COMMENCE jiggery pokery
			global $post;
			// Temporarily store the current $post var so we can use the template tags
			$original_post = $post;
			foreach ( $posts AS $post ) : 
				setup_postdata($post);
			?>
				<li class="item">

					<?php if ( al_is_author_post() ) { ?>
						<div class="thebyline">This is an author post</div>
					<?php } ?>
					<?php if ( al_is_category_post() ) { ?>
						<div class="thebyline">This is a <?php the_category(', ') ?> category post</div>
					<?php } ?>
					<div class="thetext">
						<h4>
							<a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>">
								<?php the_title(); ?>
							</a>
						</h4>
						<div class="date">Posted <?php the_time('F jS, Y') ?>  by <?php the_author() ?></div>
						<div class="theexcerpt">
							<?php the_excerpt('Read the rest of this entry &raquo;'); ?>
						</div>

					</div>
				</li>
			<?php 
			endforeach; 
			// END jiggery pokery and restore the $post var for other things to use
			$post = $original_post;
			setup_postdata($post);
			?>
		</ul>
	</div>
</div>
