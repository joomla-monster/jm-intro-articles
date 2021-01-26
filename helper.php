<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_jm_articles_category
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\String\StringHelper;

$com_path = JPATH_SITE . '/components/com_content/';

JLoader::register('ContentHelperRoute', $com_path . 'helpers/route.php');
JModelLegacy::addIncludePath($com_path . 'models', 'ContentModel');

/**
 * Helper for mod_jm_articles_category
 *
 * @package     Joomla.Site
 * @subpackage  mod_jm_articles_category
 *
 * @since       1.6
 */
abstract class ModJMArticlesCategoryHelper
{
	/**
	 * Get a list of articles from a specific category
	 *
	 * @param   \Joomla\Registry\Registry  &$params  object holding the models parameters
	 *
	 * @return  mixed
	 *
	 * @since  1.6
	 */
	public static function getList(&$params)
	{
		// Get an instance of the generic articles model
		$articles = JModelLegacy::getInstance('Articles', 'ContentModel', array('ignore_request' => true));

		// Set application parameters in model
		$app       = JFactory::getApplication();
		$appParams = $app->getParams();
		$articles->setState('params', $appParams);

		$count = (int) $params->get('count', 0);
		$skip_items = (int) $params->get('skip_items', 0);

		// Set the filters based on the module params
		$articles->setState('list.start', 0);
		$articles->setState('list.limit', $count + $skip_items);
		$articles->setState('filter.published', 1);

		// Access filter
		$access     = !JComponentHelper::getParams('com_content')->get('show_noauth');
		$authorised = JAccess::getAuthorisedViewLevels(JFactory::getUser()->get('id'));
		$articles->setState('filter.access', $access);

		// Prep for Normal or Dynamic Modes
		$mode = $params->get('mode', 'normal');

		switch ($mode)
		{
			case 'dynamic' :
				$option = $app->input->get('option');
				$view   = $app->input->get('view');

				if ($option === 'com_content')
				{
					switch ($view)
					{
						case 'category' :
							$catids = array($app->input->getInt('id'));
							break;
						case 'categories' :
							$catids = array($app->input->getInt('id'));
							break;
						case 'article' :
							if ($params->get('show_on_article_page', 1))
							{
								$article_id = $app->input->getInt('id');
								$catid      = $app->input->getInt('catid');

								if (!$catid)
								{
									// Get an instance of the generic article model
									$article = JModelLegacy::getInstance('Article', 'ContentModel', array('ignore_request' => true));

									$article->setState('params', $appParams);
									$article->setState('filter.published', 1);
									$article->setState('article.id', (int) $article_id);
									$item   = $article->getItem();
									$catids = array($item->catid);
								}
								else
								{
									$catids = array($catid);
								}
							}
							else
							{
								// Return right away if show_on_article_page option is off
								return;
							}
							break;

						case 'featured' :
						default:
							// Return right away if not on the category or article views
							return;
					}
				}
				else
				{
					// Return right away if not on a com_content page
					return;
				}

				break;

			case 'normal' :
			default:
				$catids = $params->get('catid');
				$articles->setState('filter.category_id.include', (bool) $params->get('category_filtering_type', 1));
				break;
		}

		// Category filter
		if ($catids)
		{
			if ($params->get('show_child_category_articles', 0) && (int) $params->get('levels', 0) > 0)
			{
				// Get an instance of the generic categories model
				$categories = JModelLegacy::getInstance('Categories', 'ContentModel', array('ignore_request' => true));
				$categories->setState('params', $appParams);
				$levels = $params->get('levels', 1) ?: 9999;
				$categories->setState('filter.get_children', $levels);
				$categories->setState('filter.published', 1);
				$categories->setState('filter.access', $access);
				$additional_catids = array();

				foreach ($catids as $catid)
				{
					$categories->setState('filter.parentId', $catid);
					$recursive = true;
					$items     = $categories->getItems($recursive);

					if ($items)
					{
						foreach ($items as $category)
						{
							$condition = (($category->level - $categories->getParent()->level) <= $levels);

							if ($condition)
							{
								$additional_catids[] = $category->id;
							}
						}
					}
				}

				$catids = array_unique(array_merge($catids, $additional_catids));
			}

			$articles->setState('filter.category_id', $catids);
		}

		// Ordering
		$ordering = $params->get('article_ordering', 'a.ordering');

		switch ($ordering)
		{
			case 'random':
				$articles->setState('list.ordering', JFactory::getDbo()->getQuery(true)->Rand());
				break;

			case 'rating_count':
			case 'rating':
				$articles->setState('list.ordering', $ordering);
				$articles->setState('list.direction', $params->get('article_ordering_direction', 'ASC'));

				if (!JPluginHelper::isEnabled('content', 'vote'))
				{
					$articles->setState('list.ordering', 'a.ordering');
				}

				break;

			default:
				$articles->setState('list.ordering', $ordering);
				$articles->setState('list.direction', $params->get('article_ordering_direction', 'ASC'));
				break;
		}

		// New Parameters
		$articles->setState('filter.featured', $params->get('show_front', 'show'));
		$articles->setState('filter.author_id', $params->get('created_by', ''));
		$articles->setState('filter.author_id.include', $params->get('author_filtering_type', 1));
		$articles->setState('filter.author_alias', $params->get('created_by_alias', ''));
		$articles->setState('filter.author_alias.include', $params->get('author_alias_filtering_type', 1));
		$excluded_articles = $params->get('excluded_articles', '');

		if ($excluded_articles)
		{
			$excluded_articles = explode("\r\n", $excluded_articles);
			$articles->setState('filter.article_id', $excluded_articles);

			// Exclude
			$articles->setState('filter.article_id.include', false);
		}

		$date_filtering = $params->get('date_filtering', 'off');

		if ($date_filtering !== 'off')
		{
			$articles->setState('filter.date_filtering', $date_filtering);
			$articles->setState('filter.date_field', $params->get('date_field', 'a.created'));
			$articles->setState('filter.start_date_range', $params->get('start_date_range', '1000-01-01 00:00:00'));
			$articles->setState('filter.end_date_range', $params->get('end_date_range', '9999-12-31 23:59:59'));
			$articles->setState('filter.relative_date', $params->get('relative_date', 30));
		}

		// Filter by language
		$articles->setState('filter.language', $app->getLanguageFilter());

		$items = $articles->getItems();

		// Display options
		$show_date        = $params->get('show_date', 0);
		$show_date_field  = $params->get('show_date_field', 'created');
		$show_date_format = $params->get('show_date_format', 'Y-m-d H:i:s');
		$show_category    = $params->get('show_category', 0);
		$show_hits        = $params->get('show_hits', 0);
		$show_author      = $params->get('show_author', 0);
		$show_introtext   = $params->get('show_introtext', 0);
		$introtext_limit  = $params->get('introtext_limit', 100);

		// Find current Article ID if on an article page
		$option = $app->input->get('option');
		$view   = $app->input->get('view');

		if ($option === 'com_content' && $view === 'article')
		{
			$active_article_id = $app->input->getInt('id');
		}
		else
		{
			$active_article_id = 0;
		}

		//skip items
		if( $skip_items > 0 ) {
			for ($i = 0; $i < $skip_items; $i++) {
				unset($items[$i]);
			}
		}

		// Prepare data for display using display options
		foreach ($items as &$item)
		{

			$item->slug    = $item->id . ':' . $item->alias;

			/** @deprecated Catslug is deprecated, use catid instead. 4.0 **/
			$item->catslug = $item->catid . ':' . $item->category_alias;

			if ($access || in_array($item->access, $authorised))
			{
				// We know that user has the privilege to view the article
				$item->link = JRoute::_(ContentHelperRoute::getArticleRoute($item->slug, $item->catid, $item->language));
			}
			else
			{
				$menu      = $app->getMenu();
				$menuitems = $menu->getItems('link', 'index.php?option=com_users&view=login');

				if (isset($menuitems[0]))
				{
					$Itemid = $menuitems[0]->id;
				}
				elseif ($app->input->getInt('Itemid') > 0)
				{
					// Use Itemid from requesting page only if there is no existing menu
					$Itemid = $app->input->getInt('Itemid');
				}

				$item->link = JRoute::_('index.php?option=com_users&view=login&Itemid=' . $Itemid);
			}

			// Used for styling the active article
			$item->active      = $item->id == $active_article_id ? 'active' : '';
			$item->displayDate = '';

			if ($show_date)
			{
				$item->displayDate = JHtml::_('date', $item->$show_date_field, $show_date_format);
			}

			if ($item->catid)
			{
				$item->displayCategoryLink  = JRoute::_(ContentHelperRoute::getCategoryRoute($item->catid));
				$item->displayCategoryTitle = $show_category ? '<a href="' . $item->displayCategoryLink . '">' . $item->category_title . '</a>' : '';
			}
			else
			{
				$item->displayCategoryTitle = $show_category ? $item->category_title : '';
			}

			$item->displayHits       = $show_hits ? $item->hits : '';
			$item->displayAuthorName = $show_author ? $item->author : '';

			if ($show_introtext)
			{
				$item->introtext = JHtml::_('content.prepare', $item->introtext, '', 'mod_jm_articles_category.content');
				$item->introtext = self::_cleanIntrotext($item->introtext);
			}

			$item->displayIntrotext = $show_introtext ? self::truncate($item->introtext, $introtext_limit) : '';
			$item->displayReadmore  = $item->alternative_readmore;

			//thumbnails
			self::createThumbs($params, $item);

		}

		return $items;
	}

	/**
	 * Strips unnecessary tags from the introtext
	 *
	 * @param   string  $introtext  introtext to sanitize
	 *
	 * @return mixed|string
	 *
	 * @since  1.6
	 */
	public static function _cleanIntrotext($introtext)
	{
		$introtext = str_replace(array('<p>','</p>'), ' ', $introtext);
		$introtext = strip_tags($introtext, '<a>');
		$introtext = trim($introtext);

		return $introtext;
	}

	/**
	 * Method to truncate introtext
	 *
	 * The goal is to get the proper length plain text string with as much of
	 * the html intact as possible with all tags properly closed.
	 *
	 * @param   string   $html       The content of the introtext to be truncated
	 * @param   integer  $maxLength  The maximum number of charactes to render
	 *
	 * @return  string  The truncated string
	 *
	 * @since   1.6
	 */
	public static function truncate($html, $maxLength = 0)
	{
		$baseLength = strlen($html);

		// First get the plain text string. This is the rendered text we want to end up with.
		$ptString = JHtml::_('string.truncate', $html, $maxLength, $noSplit = true, $allowHtml = false);

		for ($maxLength; $maxLength < $baseLength;)
		{
			// Now get the string if we allow html.
			$htmlString = JHtml::_('string.truncate', $html, $maxLength, $noSplit = true, $allowHtml = true);

			// Now get the plain text from the html string.
			$htmlStringToPtString = JHtml::_('string.truncate', $htmlString, $maxLength, $noSplit = true, $allowHtml = false);

			// If the new plain text string matches the original plain text string we are done.
			if ($ptString === $htmlStringToPtString)
			{
				return $htmlString;
			}

			// Get the number of html tag characters in the first $maxlength characters
			$diffLength = strlen($ptString) - strlen($htmlStringToPtString);

			// Set new $maxlength that adjusts for the html tags
			$maxLength += $diffLength;

			if ($baseLength <= $maxLength || $diffLength <= 0)
			{
				return $htmlString;
			}
		}

		return $html;
	}

	/**
	 * Groups items by field
	 *
	 * @param   array   $list                        list of items
	 * @param   string  $fieldName                   name of field that is used for grouping
	 * @param   string  $article_grouping_direction  ordering direction
	 * @param   null    $fieldNameToKeep             field name to keep
	 *
	 * @return  array
	 *
	 * @since   1.6
	 */
	public static function groupBy($list, $fieldName, $article_grouping_direction, $fieldNameToKeep = null)
	{
		$grouped = array();

		if (!is_array($list))
		{
			if ($list == '')
			{
				return $grouped;
			}

			$list = array($list);
		}

		foreach ($list as $key => $item)
		{
			if (!isset($grouped[$item->$fieldName]))
			{
				$grouped[$item->$fieldName] = array();
			}

			if ($fieldNameToKeep === null)
			{
				$grouped[$item->$fieldName][$key] = $item;
			}
			else
			{
				$grouped[$item->$fieldName][$key] = $item->$fieldNameToKeep;
			}

			unset($list[$key]);
		}

		$article_grouping_direction($grouped);

		return $grouped;
	}

	/**
	 * Groups items by date
	 *
	 * @param   array   $list                        list of items
	 * @param   string  $type                        type of grouping
	 * @param   string  $article_grouping_direction  ordering direction
	 * @param   string  $month_year_format           date format to use
	 *
	 * @return  array
	 *
	 * @since   1.6
	 */
	public static function groupByDate($list, $type = 'year', $article_grouping_direction, $month_year_format = 'F Y')
	{
		$grouped = array();

		if (!is_array($list))
		{
			if ($list == '')
			{
				return $grouped;
			}

			$list = array($list);
		}

		foreach ($list as $key => $item)
		{
			switch ($type)
			{
				case 'month_year' :
					$month_year = StringHelper::substr($item->created, 0, 7);

					if (!isset($grouped[$month_year]))
					{
						$grouped[$month_year] = array();
					}

					$grouped[$month_year][$key] = $item;
					break;

				case 'year' :
				default:
					$year = StringHelper::substr($item->created, 0, 4);

					if (!isset($grouped[$year]))
					{
						$grouped[$year] = array();
					}

					$grouped[$year][$key] = $item;
					break;
			}

			unset($list[$key]);
		}

		$article_grouping_direction($grouped);

		if ($type === 'month_year')
		{
			foreach ($grouped as $group => $items)
			{
				$date                      = new JDate($group);
				$formatted_group           = $date->format($month_year_format);
				$grouped[$formatted_group] = $items;

				unset($grouped[$group]);
			}
		}

		return $grouped;
	}

	public static function createThumbs($params, &$item, $force = false) {

		$show_image = $params->get('show_image', 1);
		$image_source = $params->get('image_source', 1);
		if( $show_image !=1 ) {
			return;
		}

		$images = json_decode($item->images);

		if( $image_source == 1 && !empty($images->image_intro) ) {
			$image = $images->image_intro;
			$item->thumbnail_alt = $images->image_intro_alt;
		} else if( !empty($images->image_fulltext) ) {
			$image = $images->image_fulltext;
			$item->thumbnail_alt = $images->image_fulltext_alt;
		} else if( !empty($images->image_intro) ) {
			$image = $images->image_intro;
			$item->thumbnail_alt = $images->image_intro_alt;
		} else {
			$image = false;
		}

		//set default image
		$item->thumbnail = $image;

		$imgPath = ( !empty($image) ) ? JPATH_SITE . DIRECTORY_SEPARATOR . ltrim($image, DIRECTORY_SEPARATOR) : false;
		$param_width = $params->get('image_width', 0);
		$param_height = $params->get('image_height', 0);

		if ( !empty($imgPath) && JFile::exists($imgPath) && ( !empty($param_width) || !empty($param_height) ) ) {

			$creationMethod = $params->get('image_resizing', 2); //default method - fit to width

			// 1 force resize
			// 2 fit to width
			// 3 fit to height
			// 4 crop

			if( ($creationMethod == 2 && empty($param_width)) || ($creationMethod == 3 && empty($param_height)) ) {
				return;
			}

			// source object
			$sourceImage = new JImage($imgPath);
			$srcHeight = $sourceImage->getHeight();
			$srcWidth = $sourceImage->getWidth();

			$thumbWidth = ( !empty($param_width) ) ? (int) $param_width : $srcWidth;
			$thumbHeight = ( !empty($param_height) ) ? (int) $param_height : $srcHeight;

			$ratio = $srcWidth / $srcHeight;

			$imgProperties = JImage::getImageFileProperties($imgPath);

			// generate thumb name
			$filename = JFile::getName($imgPath);
			$fileExtension = JFile::getExt($filename);
			$sourceType = ($image_source == 1) ? 'intro' : 'full';

			if ( $creationMethod == 2 ) { //height auto (fit to width)
				$targetHeight = $thumbWidth / $ratio;
				$thumbHeight = floor($targetHeight);
				$param_height = 'auto';
				$dirname = $sourceType . '_w_' . $param_width .'x' . $param_height;
			} elseif( $creationMethod == 3 ) { //width auto (fit to height)
				$targetWidth = $thumbHeight * $ratio;
				$thumbWidth = floor($targetWidth);
				$param_width = 'auto';
				$dirname = $sourceType . '_h_' . $param_width .'x' . $param_height;
			} elseif( $creationMethod == 4 ) { //crop
				$dirname = $sourceType . '_c_' . $param_width .'x' . $param_height;
			} else { //resize
				$dirname = $sourceType . '_r_' . $param_width .'x' . $param_height;
			}

			$moduleID = 'mod' . $params->get('module_id', 0);
			$itemID = $item->id;

			$mediaFolder = JPATH_SITE . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . basename(__DIR__) . DIRECTORY_SEPARATOR . $moduleID;
			$thumbsFolder = $mediaFolder . DIRECTORY_SEPARATOR . $dirname;
			$thumbFileName = $thumbsFolder . DIRECTORY_SEPARATOR . $itemID . '_' . $filename;

			// check | try to create thumbsfolder
			if (JFolder::exists($thumbsFolder) || JFolder::create($thumbsFolder)) {

				// try to generate the thumb if needed
				if( !file_exists($thumbFileName) || filemtime($imgPath) > filemtime($thumbFileName) || $force == true ) {

					// remove other sizes if exists
					$removeDirectories = JFolder::folders($mediaFolder, '.', 1, true, array($dirname) );

					if( is_array($removeDirectories) ) {
						foreach( $removeDirectories as $dir ) {
							JFolder::delete($dir);
						}
					}

					if ($creationMethod == 4) {
						// auto crop centered coordinates
						$left = round(($srcWidth - $thumbWidth) / 2);
						$top = round(($srcHeight - $thumbHeight) / 2);
						// crop image
						$thumb = $sourceImage->crop($thumbWidth, $thumbHeight, $left, $top, true);
					} else {
						// resize image
						$thumb = $sourceImage->resize($thumbWidth, $thumbHeight, true, $creationMethod);
					}
					// create file
					$thumb->toFile($thumbFileName, $imgProperties->type);
				}

				//get existing thumb
				if ( JFile::exists($thumbFileName) ) {
					$item->thumbnail = str_replace(JPATH_SITE, JUri::root(true), $thumbFileName);
				}
			}

		}
	}

	public static function displayItem($params, $item) {

		$image_align = $params->get('image_align', 1);
		$image_position = $params->get('image_position', 1);
		$image_linked = $params->get('image_linked', 0);

		if( $image_align == 2 ) {
			$align_class = 'pull-left';
		} elseif( $image_align == 3 ) {
			$align_class = 'pull-right';
		} else {
			$align_class = '';
		}

		if( !empty($item->thumbnail) && $image_position == 1 ) {
			$caption = ( !empty($item->thumbnail_alt) ) ? $item->thumbnail_alt : '';
			echo '<div class="jmm-image mod-article-image ' . $align_class . '">';
			if( $image_linked == 1 ) {
				echo '<a href="' . $item->link . '">';
			}
			echo '<img src="' . $item->thumbnail . '" alt="' . $caption . '" />';
			if( $image_linked == 1 ) {
				echo '</a>';
			}
			echo '</div>';
		}

		if( $image_position == 1 && $image_align == 1 ) {
			echo '<div class="jmm-text">';
		}

		if ($params->get('link_titles') == 1) {
			echo '<a class="jmm-title mod-articles-category-title ' . $item->active . '" href="' . $item->link . '">';
			echo $item->title;
			echo '</a>';
		} else {
			echo '<span class="jmm-title mod-articles-category-title">' . $item->title . '</span>';
		}

		if( !empty($item->thumbnail) && $image_position == 2 ) {
			$caption = ( !empty($item->thumbnail_alt) ) ? $item->thumbnail_alt : '';
			echo '<div class="jmm-image mod-article-image ' . $align_class . '">';
			if( $image_linked == 1 ) {
				echo '<a href="' . $item->link . '">';
			}
			echo '<img src="' . $item->thumbnail . '" alt="' . $caption . '" />';
			if( $image_linked == 1 ) {
				echo '</a>';
			}
			echo '</div>';
		}

		if ($item->displayHits) {
			echo '<span class="jmm-hits mod-articles-category-hits">' . $item->displayHits . '</span>';
		}

		if ($params->get('show_author')) {
			echo '<span class="jmm-author mod-articles-category-writtenby">' . $item->displayAuthorName . '</span>';
		}

		if ($item->displayCategoryTitle) {
			echo '<span class="jmm-category mod-articles-category-category">' . $item->displayCategoryTitle . '</span>';
		}

		if ($item->displayDate) {
			echo '<span class="jmm-date mod-articles-category-date">' . $item->displayDate . '</span>';
		}

		if ($params->get('show_introtext')) {
			echo '<p class="jmm-intortext mod-articles-category-introtext">' . $item->displayIntrotext . '</p>';
		}

		if ($params->get('show_readmore')) {
			echo '<p class="jmm-readmore"><a class="readmore" href="' . $item->link . '">';
				if ($item->params->get('access-view') == false) {
					echo JText::_('MOD_JM_ARTICLES_CATEGORY_REGISTER_TO_READ_MORE');
				} elseif ($readmore = $item->alternative_readmore) {
					echo $readmore;
					echo JHtml::_('string.truncate', $item->title, $params->get('readmore_limit'));
				} elseif ($params->get('show_readmore_title', 0) == 0) {
					echo JText::sprintf('MOD_JM_ARTICLES_CATEGORY_READ_MORE_TITLE');
				} else {
					echo JText::_('MOD_JM_ARTICLES_CATEGORY_READ_MORE');
					echo JHtml::_('string.truncate', $item->title, $params->get('readmore_limit'));
				}
			echo '</a></p>';
		}

		if( $image_position == 1 && $image_align == 1 ) {
			echo '</div>';
		}

	}
}
