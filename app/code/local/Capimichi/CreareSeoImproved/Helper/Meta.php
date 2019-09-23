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
            
        } else if (Mage::registry('current_category')) {
            $registry->_code = 'category';
            $registry->_model = Mage::registry('current_category');
            
            return $registry;
            
        } else if (Mage::app()->getFrontController()->getRequest()->getRouteName() === 'cms') {
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
        } else if ($product->getData($attribute)) {
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
    
    /**
     * Apply parent category
     *
     * @author Michele Capicchioni <capimichi@gmail.com>
     *
     *
     * @param $pagetype
     * @param $string
     *
     * @return mixed
     */
    public function applyParent($pagetype, $string)
    {
        if ($pagetype->_code == "category") {
            
            while (preg_match("/\[parent_category(.*?)\]/is", $string, $parentCategoryData)) {
                $parentCategoryData = $parentCategoryData[1];
                
                $properties = [
                    'level'          => 1,
                    'left_separator' => '',
                    'ignore_default' => 1,
                ];
                
                foreach ($properties as $key => $value) {
                    if (preg_match("/" . $key . "=\"(.*?)\"/is", $parentCategoryData, $v)) {
                        $properties[$key] = $v[1];
                    }
                }
                
                $category = $pagetype->_model;
                
                $parent = $category;
                $currentParentLevel = 0;
                while ($currentParentLevel < intval($properties['level'])) {
                    if ($parent->getParentId()) {
                        $parent = Mage::getModel('catalog/category')->load($parent->getParentId());
                    }
                    $currentParentLevel++;
                }
                
                if (intval($properties['ignore_default'])) {
                    if ($parent->getPath()) {
                        if (count(explode("/", $parent->getPath())) < 3) {
                            $parent = null;
                        }
                    }
                }
                
                $leftSeparator = $properties['left_separator'];
                if (!$parent) {
                    $leftSeparator = "";
                }
                
                $replaceString = [
                    $leftSeparator,
                    $parent ? $parent->getName() : '',
                ];
                
                $string = preg_replace("/\[parent_category.*?\]/is", $replaceString, $string, 1);
            }
        }
        
        return $string;
    }
    
    public function applyCustomAttriubte($pagetype, $string)
    {
        if ($pagetype->_code == "product") {
            while (preg_match("/\[custom_attribute_(.*?)\]/is", $string, $code)) {
                $code = $code[1];
                $product = $pagetype->_model;
                
                $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
                $type = $attribute->getFrontendInput();
                $value = "";
                switch ($type) {
                    case "select":
                        $optionId = $product->getData($code);
                        if ($optionId) {
                            $optionLabel = $attribute->getFrontend()->getOption($optionId);
                        } else {
                            $optionLabel = "";
                        }
                        $value = $optionLabel;
                        break;
                    case "text":
                    case "textarea":
                        $value = $product->getData($code);
                        break;
                }
                $string = str_replace("[custom_attribute_" . $code . "]", $value, $string);
            }
        }
        
        return $string;
    }
}
