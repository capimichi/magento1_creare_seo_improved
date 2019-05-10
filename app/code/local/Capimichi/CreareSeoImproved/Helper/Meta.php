<?php

class Capimichi_CreareSeoImproved_Helper_Meta extends Creare_CreareSeoCore_Helper_Meta
{
	public function getDefaultTitle($pagetype)
	{
		$title = $this->config($pagetype . '_title');
		return $this->shortcode($title);
	}

	public function getDefaultMetaDescription($pagetype)
	{
		$metadesc = $this->config($pagetype . '_metadesc');
		return $this->shortcode($metadesc);
	}

	public function getPageType()
	{
		$registry = new Varien_Object;

		if (Mage::registry('current_product')) {
			$registry->_code = 'product';
			$registry->_model = Mage::registry('current_product');

			return $registry;

		} elseif (Mage::registry('current_category')) {
			$registry->_code = 'category';
			$registry->_model = Mage::registry('current_category');

			return $registry;

		} elseif (Mage::app()->getFrontController()->getRequest()->getRouteName() === 'cms') {
			$registry->_code = 'cms';
			$registry->_model = Mage::getSingleton('cms/page');

			return $registry;

		} else {
			return false;

		}
	}

	public function config($path)
	{
		return Mage::getStoreConfig('creareseocore/metadata/' . $path);
	}

	public function shortcode($string)
	{
		$pagetype = $this->getPageType();

		$string = $this->applyParent($pagetype, $string);
		$string = $this->applyCustomAttriubte($pagetype, $string);

		preg_match_all("/\[(.*?)\]/", $string, $matches);

		for ($i = 0; $i < count($matches[1]); $i++) {
			$tag = $matches[1][$i];

			if ($tag === "store") {
				$string = str_replace($matches[0][$i], Mage::app()->getStore()->getName(), $string);
			} else {

				switch ($pagetype->_code) {
					case 'product' :
					$attribute = $this->productAttribute($pagetype->_model, $tag);
					break;

					case 'category' :
					$attribute = $this->attribute($pagetype->_model, $tag);
					break;

					case 'cms' :
					$attribute = $this->attribute($pagetype->_model, $tag);
					break;

				}
				$string = str_replace($matches[0][$i], $attribute, $string);
			}
		}

		return $string;
	}

	public function productAttribute($product, $attribute)
	{
		$data = '';
		if ($attribute == "categories" || $attribute == "first_category") {
			$catIds = $product->getCategoryIds();

			if (empty($catIds)) {
				return $data;
			}

			$categories = Mage::getResourceModel('catalog/category_collection')
			->addAttributeToSelect('name')
			->addAttributeToFilter('entity_id', $catIds)
			->addIsActiveFilter();

			if ($categories->count() < 1) {
				return $data;
			}

			if ($attribute == "categories") {
				$categoryNames = [];

				foreach ($categories as $category) {
					$categoryNames[] = $category->getName();
				}

				$data = implode(", ", $categoryNames);
			}

			if ($attribute == "first_category") {
				$data = $categories->getFirstItem()->getName();
			}
		} elseif ($product->getData($attribute)) {
			$data = $product->getResource()
			->getAttribute($attribute)
			->getFrontend()
			->getValue($product);
		}

		return $data;
	}

	public function attribute($model, $attribute)
	{
		if ($model->getData($attribute)) {
			return $model->getData($attribute);
		}
	}

	public function applyParent($pagetype, $string)
	{

		if ($pagetype->_code == "category") {
			$category = $pagetype->_model;
			$parent = Mage::getModel('catalog/category')->load($category->getParentId());
			$parentName = "";
			if ($parent) {
				$parentName = $parent->getName();
			}
			$string = str_replace("[parent_category]", $parentName, $string);
		}

		return $string;
	}

	public function applyCustomAttriubte($pagetype, $string)
	{
		if ($pagetype->_code == "product") {
			while (preg_match("/\[custom_attribute_(.*?)\]/is", $string, $code)) {
				$code = $code[1];
				$product = $pagetype->_model;
				$string = str_replace("[custom_attribute_" . $code . "]", $product->getAttribteText($code), $string);
			}
		}

		return $string;
	}
}
