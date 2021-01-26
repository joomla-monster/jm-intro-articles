<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_jm_articles_category
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

$params->set('module_id', $module->id);
$id = 'jm-category-module-' . $module->id;

// Include the helper functions only once
JLoader::register('ModJMArticlesCategoryHelper', __DIR__ . '/helper.php');

$input = JFactory::getApplication()->input;

// Prep for Normal or Dynamic Modes
$mode   = $params->get('mode', 'normal');
$idbase = null;

$doc = JFactory::getDocument();
$theme = $params->get('theme', 1);
$theme_class = ( $theme == 1 ) ? 'default' : 'override';

if( $theme == 1 ) { //default
	$doc->addStyleSheet(JURI::root(true).'/modules/' . basename(__DIR__) . '/assets/default.css');
}

switch ($mode)
{
	case 'dynamic' :
		$option = $input->get('option');
		$view   = $input->get('view');

		if ($option === 'com_content')
		{
			switch ($view)
			{
				case 'category' :
					$idbase = $input->getInt('id');
					break;
				case 'categories' :
					$idbase = $input->getInt('id');
					break;
				case 'article' :
					if ($params->get('show_on_article_page', 1))
					{
						$idbase = $input->getInt('catid');
					}
					break;
			}
		}
		break;
	case 'normal' :
	default:
		$idbase = $params->get('catid');
		break;
}

$cacheid = md5(serialize(array ($idbase, $module->module, $module->id)));

$cacheparams               = new stdClass;
$cacheparams->cachemode    = 'id';
$cacheparams->class        = 'ModJMArticlesCategoryHelper';
$cacheparams->method       = 'getList';
$cacheparams->methodparams = $params;
$cacheparams->modeparams   = $cacheid;

$list = JModuleHelper::moduleCache($module, $params, $cacheparams);

if (!empty($list))
{
	$grouped                    = false;
	$article_grouping           = $params->get('article_grouping', 'none');
	$article_grouping_direction = $params->get('article_grouping_direction', 'ksort');
	$moduleclass_sfx            = htmlspecialchars($params->get('moduleclass_sfx'), ENT_COMPAT, 'UTF-8');
	$item_heading               = $params->get('item_heading');

	if ($article_grouping !== 'none')
	{
		$grouped = true;

		switch ($article_grouping)
		{
			case 'year' :
			case 'month_year' :
				$list = ModJMArticlesCategoryHelper::groupByDate($list, $article_grouping, $article_grouping_direction, $params->get('month_year_format', 'F Y'));
				break;
			case 'author' :
			case 'category_title' :
				$list = ModJMArticlesCategoryHelper::groupBy($list, $article_grouping, $article_grouping_direction);
				break;
			default:
				break;
		}
	}

	$image_align = $params->get('image_align', 1);
	$image_position = $params->get('image_position', 1);
	$carousel = $params->get('carousel', 0);
	$columns = $params->get('columns', 1);
	$auto_play = $params->get('auto_play', 0);
	$show_indicators = $params->get('show_indicators', 0);
	$show_nav = $params->get('show_nav', 0);
	$interval = $params->get('play_interval', 0);

	if( $carousel == 1 ) {

		$interval = intval($interval);
		$play = ( $auto_play == 1 && $interval > 0 ) ? $interval : 0;

		JHtml::_('bootstrap.framework');

		$doc->addScript(JURI::root(true).'/modules/' . basename(__DIR__) . '/assets/jquery.touchSwipe.min.js');

		$doc->addScriptDeclaration('
			jQuery(document).ready(function(){
				jQuery(\'#' . $id . '\')[0].slide = null;
				jQuery(\'#' . $id . '\').carousel({
					interval: ' . $play . '
				});
				jQuery(\'#' . $id . '\').swipe({
					swipe: function(event, direction, distance, duration, fingerCount, fingerData) {
						if (direction == \'left\') {
							jQuery(this).carousel(\'next\');
						}
						if (direction == \'right\') {
							jQuery(this).carousel(\'prev\');
						}
					},
					allowPageScroll: \'vertical\'
				});
			});
		');

	}

	require JModuleHelper::getLayoutPath('mod_jm_articles_category', $params->get('layout', 'default'));
}
