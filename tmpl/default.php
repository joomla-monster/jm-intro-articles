<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_jm_articles_category
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

$i = 0;
$g = 0;
$row = 0;

$items = count($list);
$carousel_class = ( $carousel == 1 && $grouped == 0 ) ? 'carousel slide carousel-fade' : '';
$carousel_in_class = ( $carousel == 1 && $grouped == 0 ) ? 'carousel-inner' : '';
$item_class = ( $carousel == 1 && $grouped == 0 ) ? 'item' : '';
$active_class = ( $carousel == 1 && $grouped == 0 ) ? 'active' : '';
$indicators_class = ( $carousel == 1 && $show_indicators == 1 ) ? 'indicators' : '';

?>
<div id="<?php echo $id; ?>" class="jm-category-module clearfix <?php echo $carousel_class . ' ' . $indicators_class . ' ' . $theme_class . ' ' . $moduleclass_sfx; ?>">
	<?php if ($grouped) : // groupped view ?>
		<?php foreach ($list as $group_name => $group) :
			$g++;
		?>
			<div class="jmm-group jmm-group<?php echo $g; ?>">
				<div class="jmm-rows <?php echo 'rows-' . $columns . ' ' . $carousel_in_class; ?>">
					<div class="jmm-row row-<? echo $row . ' ' . $item_class; ?>">

						<?php foreach ($group as $item) :

						if($i % $columns == 0 && $i > 0) {
							$row++;
							echo '</div><div class="jmm-row row-' . $row . ' ' . $item_class . '">';
						}

						$i++;

						?>
						<div class="jmm-item jmm-item<?php echo $i; ?>">

							<?php
								ModJMArticlesCategoryHelper::displayItem($params, $item);
							?>

						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>

	<?php else : // normal view ?>
		<div class="jmm-rows <?php echo 'rows-' . $columns . ' ' . $carousel_in_class; ?>">
			<div class="jmm-row row-<?php echo $row . ' ' . $item_class . ' ' . $active_class; ?>">

				<?php foreach ($list as $item) :

				if($i % $columns == 0 && $i > 0) {
					$row++;
					echo '</div><div class="jmm-row row-' . $row . ' ' . $item_class . '">';
				}

				$i++;

				?>
				<div class="jmm-item jmm-item<?php echo $i; ?>">

				<?php
					ModJMArticlesCategoryHelper::displayItem($params, $item);
				?>

				</div>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if( $carousel == 1 && $show_indicators == 1 && $grouped == 0 ) :

	$visible = ceil($items/$columns);

	?>
	<ol class="carousel-indicators">
		<?php for($i = 0; $i < $visible; $i++ ) {
			$class = ( $i == 0 ) ? ' class="active"' : '';
			$idi = $i + 1;
			$itemID = $id . '-' . $idi;
		?>
		<li data-target="#<?php echo $id; ?>" data-slide-to="<?php echo $i; ?>"<?php echo $class; ?>><a href="#<?php echo $itemID; ?>" class="sr-only"><span class="name"><?php echo JText::_('MOD_JM_ARTICLES_CATEGORY_INDICATOR_LABEL'); ?></span><span class="number"><?php echo $idi; ?></span></a></li>
		<?php } ?>
	</ol>
	<?php endif; ?>
	<?php if( $carousel == 1 && $show_nav == 1 && $grouped == 0 ) : ?>
	<a class="carousel-control left" href="#<?php echo $id; ?>" data-slide="prev" role="button"><span class="sr-only"><?php echo JText::_('MOD_JM_ARTICLES_CATEGORY_NAV_PREV_LABEL'); ?></span><span class="arrow"></span></a>
	<a class="carousel-control right" href="#<?php echo $id; ?>" data-slide="next" role="button"><span class="sr-only"><?php echo JText::_('MOD_JM_ARTICLES_CATEGORY_NAV_NEXT_LABEL'); ?></span><span class="arrow"></span></a>
	<?php endif; ?>
</div>
