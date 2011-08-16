<?php
/* 
 * A class for storing states within a country.
 * 
 * @package LRC Locations Module
 * @author Left, Right & Centre
 * @author Simon Elvery
 */

class LRC_State extends DataObject {
	static $singular_name = 'State';
	static $plural_name = 'States';
	
	static $db = array(
		'Name' => 'Varchar(60)',
		'Abbreviation' => 'Varchar(10)'
	);

	static $has_one = array(
		'Country' => 'LRC_Country'
	);

	static $has_many = array(
		'Locations' => 'LRC_Location'
	);
}

?>
