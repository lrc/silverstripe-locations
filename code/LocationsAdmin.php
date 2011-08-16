<?php

/**
 * Backend AdminStores Interface
 *
 */
class LRC_LocationsAdmin extends ModelAdmin {

	public static $managed_models = array(
		'LRC_Location',
		'LRC_Country'
	);

	// These can be updated in teh config file.
	static $url_segment = 'locations'; // will be linked as /admin/products
	static $menu_title = 'Locations';


	static $model_importers = array();

}