<?php
/**
 * Grid template for Magazines category / archive.
 *
 * @package NMA_Magazines
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

$query_args = array(
	'post_type'      => NMA_MAGAZINES_POST_TYPE,
	'post_status'    => 'publish',
	'posts_per_page' => 12,
	'paged'          => $paged,
	'no_found_rows'  => false,
);

if ( is_category( 'magazines' ) ) {
	$query_args['tax_query'] = array(
		array(
			'taxonomy' => 'category',
			'field'    => 'slug',
			'terms'    => array( 'magazines' ),
		),
	);
}

$magazines_query = new WP_Query( $query_args );

// Fallback: if category assignment was missed in older entries, show published magazines anyway.
if ( is_category( 'magazines' ) && ! $magazines_query->have_posts() ) {
	$fallback_args = $query_args;
	unset( $fallback_args['tax_query'] );
	$magazines_query = new WP_Query( $fallback_args );
}
?>
<main class="nma-mag-grid-wrap">
	<div class="nma-mag-grid-inner">
		<header class="nma-mag-grid-header">
			<?php if ( is_category() ) : ?>
				<h1 class="nma-mag-grid-title"><?php single_cat_title(); ?></h1>
				<?php
				$cat_description = category_description();
				if ( ! empty( $cat_description ) ) :
					?>
					<div class="nma-mag-grid-desc"><?php echo wp_kses_post( $cat_description ); ?></div>
				<?php endif; ?>
			<?php else : ?>
				<h1 class="nma-mag-grid-title"><?php post_type_archive_title(); ?></h1>
			<?php endif; ?>
		</header>

		<?php if ( $magazines_query->have_posts() ) : ?>
			<section class="nma-mag-grid" aria-label="<?php esc_attr_e( 'Magazines', 'nma-magazines' ); ?>">
				<?php
				while ( $magazines_query->have_posts() ) :
					$magazines_query->the_post();
					$post_id       = get_the_ID();
					$doc_id        = (int) get_post_meta( $post_id, NMA_MAGAZINES_META_KEY, true );
					$document_url  = $doc_id ? wp_get_attachment_url( $doc_id ) : '';
					$document_mime = $doc_id ? get_post_mime_type( $doc_id ) : '';
					$thumb_html    = '';

					if ( has_post_thumbnail() ) {
						$thumb_html = get_the_post_thumbnail(
							$post_id,
							'medium_large',
							array(
								'class'   => 'nma-mag-thumb',
								'loading' => 'lazy',
							)
						);
					} elseif ( $doc_id && 'application/pdf' === $document_mime ) {
						// WordPress can provide a generated PDF preview image (first page) when server supports it.
						$thumb_html = wp_get_attachment_image(
							$doc_id,
							'medium_large',
							false,
							array(
								'class'   => 'nma-mag-thumb',
								'loading' => 'lazy',
								'alt'     => get_the_title(),
							)
						);
					}
					?>
					<article class="nma-mag-card">
						<a class="nma-mag-thumb-link" href="<?php the_permalink(); ?>">
							<?php if ( ! empty( $thumb_html ) ) : ?>
								<?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php else : ?>
								<div class="nma-mag-thumb nma-mag-thumb-placeholder"><?php esc_html_e( 'No Cover', 'nma-magazines' ); ?></div>
							<?php endif; ?>
						</a>

						<h2 class="nma-mag-card-title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h2>

						<div class="nma-mag-card-actions">
							<a class="nma-mag-btn nma-mag-btn-view" href="<?php the_permalink(); ?>"><?php esc_html_e( 'View', 'nma-magazines' ); ?></a>
							<?php if ( ! empty( $document_url ) ) : ?>
								<a class="nma-mag-btn nma-mag-btn-doc" href="<?php echo esc_url( $document_url ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo ( 'application/pdf' === $document_mime ) ? esc_html__( 'Preview PDF', 'nma-magazines' ) : esc_html__( 'Open Document', 'nma-magazines' ); ?>
								</a>
							<?php endif; ?>
						</div>
					</article>
				<?php endwhile; ?>
			</section>

			<?php
			$pagination = paginate_links(
				array(
					'base'      => str_replace( 999999, '%#%', esc_url( get_pagenum_link( 999999 ) ) ),
					'format'    => '?paged=%#%',
					'current'   => $paged,
					'total'     => max( 1, (int) $magazines_query->max_num_pages ),
					'type'      => 'list',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				)
			);
			if ( $pagination ) :
				?>
				<nav class="nma-mag-pagination" aria-label="<?php esc_attr_e( 'Pagination', 'nma-magazines' ); ?>">
					<?php echo wp_kses_post( $pagination ); ?>
				</nav>
			<?php endif; ?>
		<?php else : ?>
			<p class="nma-mag-empty"><?php esc_html_e( 'No magazines to display.', 'nma-magazines' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php wp_reset_postdata(); ?>

<style>
	.nma-mag-grid-wrap {
		padding: 24px 0 40px;
	}
	.nma-mag-grid-inner {
		max-width: 1100px;
		margin: 0 auto;
		padding: 0 16px;
	}
	.nma-mag-grid-title {
		margin: 0 0 16px;
		font-size: 42px;
		line-height: 1.1;
		text-transform: uppercase;
	}
	.nma-mag-grid-desc {
		margin-bottom: 20px;
	}
	.nma-mag-grid {
		display: grid;
		grid-template-columns: repeat(4, minmax(0, 1fr));
		gap: 22px;
	}
	.nma-mag-card {
		border: 1px solid #e1e1e1;
		background: #fff;
		padding: 12px;
	}
	.nma-mag-thumb {
		display: block;
		width: 100%;
		height: 320px;
		object-fit: cover;
		background: #f5f5f5;
	}
	.nma-mag-thumb-placeholder {
		display: flex;
		align-items: center;
		justify-content: center;
		color: #777;
		font-size: 14px;
		font-weight: 600;
	}
	.nma-mag-card-title {
		margin: 12px 0 10px;
		font-size: 18px;
		line-height: 1.35;
	}
	.nma-mag-card-title a {
		color: #111;
		text-decoration: none;
	}
	.nma-mag-card-actions {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
	}
	.nma-mag-btn {
		display: inline-block;
		padding: 8px 12px;
		font-size: 13px;
		line-height: 1;
		text-decoration: none;
		border: 1px solid #222;
		color: #222;
	}
	.nma-mag-btn-doc {
		background: #222;
		color: #fff;
	}
	.nma-mag-pagination {
		margin-top: 26px;
	}
	.nma-mag-pagination ul {
		list-style: none;
		padding: 0;
		margin: 0;
		display: flex;
		gap: 8px;
	}
	.nma-mag-pagination a,
	.nma-mag-pagination span {
		display: inline-block;
		padding: 7px 10px;
		border: 1px solid #ddd;
		text-decoration: none;
		color: #111;
	}
	.nma-mag-pagination .current {
		background: #111;
		color: #fff;
		border-color: #111;
	}
	.nma-mag-empty {
		border: 1px solid #ddd;
		padding: 14px;
		background: #fff;
		display: inline-block;
	}
	@media (max-width: 1024px) {
		.nma-mag-grid {
			grid-template-columns: repeat(3, minmax(0, 1fr));
		}
	}
	@media (max-width: 768px) {
		.nma-mag-grid-title {
			font-size: 32px;
		}
		.nma-mag-grid {
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 14px;
		}
		.nma-mag-thumb {
			height: 250px;
		}
	}
	@media (max-width: 480px) {
		.nma-mag-grid {
			grid-template-columns: 1fr;
		}
	}
</style>
<?php
get_footer();
