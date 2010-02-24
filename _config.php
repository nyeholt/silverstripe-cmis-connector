<?php
SeaMist::getInstance()->registerImplementation('Alfresco', 'AlfrescoSeaMistRepository');
SeaMist::getInstance()->registerImplementation('KnowledgeTree', 'KnowledgeTreeSeaMistRepository');

if (!function_exists('lcfirst')) {
	function lcfirst($string)
	{
		if (strlen($string)) {
			$string{0} = strtolower($string{0});
			return $string;
		}
	}
}
?>