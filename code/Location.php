<?php
class LRC_Location extends DataObject {

	static $singular_name = 'Location';
	static $plural_name = 'Locations';

	static $db = array(
		'Name' => 'Varchar(255)',
		'Phone' => 'Varchar(255)',
		'Fax' => 'Varchar(255)',
		'EmailAddress' => 'Varchar(255)',
        'Display' => 'Boolean',
		'Content' => 'HTMLText',
		'AddressStreet' => 'Varchar(255)',
		'AddressAdditional' => 'Text',
		'City' => 'Varchar(255)',
		'Postcode' => 'Varchar(255)',
		'Lat' => 'Decimal(10,7)',
		'Lng' => 'Decimal(10,7)',
		'URLSegment' => 'Varchar(255)'
	);
	
	static $has_one = array(
		'Country'=>'LRC_Country',
		'State'=>'LRC_State'
	);
	
	/**
	 * Automatically generate and save a unique URLSegment for each location, 
	 * automatically create or update countries and states and attempt to 
	 * automatically geolocate when appropriate.
	 */
	public function onBeforeWrite(){
		
		// URLSegment
		if( $this->Name || $this->Title ){ // Try for a name based URL segment
			$this->URLSegment = SiteTree::GenerateURLSegment(($this->Title) ? $this->Title : $this->Name);

			// Make sure there's not already an object with this URLSegment
			if($object = DataObject::get_one($this->ClassName, '`URLSegment` = \'' . $this->URLSegment.'\' AND `LRC_Location`.`ID` != '.$this->ID)){
				$this->URLSegment = $this->URLSegment . '-' . $this->ID;
			}
		} else { // If there's no name just use class name and ID
			$this->URLSegment = SiteTree::GenerateURLSegment(LRC_Location::$singular_name . '-' . $this->ID);
		}
		
		// Country and State
		$country = DataObject::get_one('LRC_Country', "`Abbreviation` = '" . Convert::raw2sql($this->Country) . "'");

		if ( !$country ) {
			$country = new LRC_Country();
			$country->Name = ( $code = Geoip::countryCode2name($this->Country) ) ? $code : $this->Country;
			$country->Abbreviation = $this->Country;
			$country->write();
		}

		$this->CountryID = $country->ID;
		
		
		// Attempt to automatically geolocate if the address has change and the lat/lon hasn't
		if ( $this->isChanged('AddressStreet') || $this->isChanged('City') || $this->isChanged('State') || $this->isChanged('Postcode') || $this->isChanged('CountryID') ) {
			
			$url = 'http://maps.googleapis.com/maps/api/geocode/xml?address=' . urlencode($this->AddressStreet . ', ' . $this->City . ', ' . $country->Name . ' ' . $this->Postcode . ', ' . $this->Country()->Name) . '&sensor=false';
			$result = simplexml_load_file($url);
			if ($result && (string) $result->status == 'OK') {
				$this->Lng = (string) $result->result->geometry->location->lng;
				$this->Lat = (string) $result->result->geometry->location->lat;
			}
		}
		
		parent::onBeforeWrite();
	}

	static $summary_fields = array(
		'Name',
		'AddressStreet',
		'Country.Name',
		'Postcode'
	);

	function getCMSFields() {
		$fields = parent::getCMSFields();

		// Load the store locator script

		// To enable the Google Map API in the administration area of SilverStripe, we have created a page called "googlemaps.php" in
		// the frontend of the website. All this page does is load the google map. We then use JavaScript to get the lng and lat info
		// from this page.
		//
		// Note: This has been done because there seems to be an issue loading the Google API directly in the SilverStripe admin area.
		// The AJAX calls for the API redirects the user to a blank page.
		
		if (!$this->Country) $this->Country = $this->Country()->Abbreviation;
		
		// Relocate the Geo info to a tab of its own
		$fields->addFieldToTab('Root.Location', new TextField('AddressStreet', 'Street Address'));
		$fields->addFieldToTab('Root.Location', new TextareaField('AddressAdditional', 'Additional Address Details'));
		$fields->addFieldToTab('Root.Location', new TextField('City', 'City/Suburb'));
		$fields->addFieldToTab('Root.Location', new TextField('State', 'State'));
		$fields->addFieldToTab('Root.Location', new DropdownField('Country', 'Country', Geoip::getCountryDropDown()));
		$fields->addFieldToTab('Root.Location', new TextField('Postcode', 'Postcode'));
		$fields->addFieldToTab('Root.Location', new TextField('Lat', 'Latitude'));
		$fields->addFieldToTab('Root.Location', new TextField('Lng', 'Longitude'));
		$fields->addFieldToTab('Root.Location', new LiteralField('Map', '<div class="field"><label>Location (automatically determined based on address above)</label><br><img src="http://maps.googleapis.com/maps/api/staticmap?center=' . $this->Lat . ',' . $this->Lng . '&zoom=5&size=400x200&maptype=roadmap&markers=color:red|label:A|' . $this->Lat . ',' . $this->Lng . '&sensor=false" alt="Map to location"/></div>'));
		
		// Remove the URLSegment field from the admin interface. It's populated automatically. 
		// @TODO: Let the user change this
		$fields->removeByName('URLSegment');

        return $fields;
	}

	/**
	 * Returns stores based on country
	 *
	 */
	public static function ListStores()
	{
		return DataObject::get('Store','','Name');
	}

	/**
	 * Returns stores based on country
     *
	 */
	public static function ListStoresBasedOnCountry($country)
	{
		return DataObject::get('Store',"CountryId = '{$country}'","Name");
	}

    /**
	 * Returns an array of the stores based on country
     *
	 */
	public static function ListCountries($country)
	{
		return DataObject::get('Country','','Name');
	}

	/**
	 * Returns an array of the countries which currently have stores.
	 */
	public static function Countries() {
             $countries = DataObject::get('Country','','Name');

             if($countries)
             {
                 return $countries->toDropdownMap('ID', 'Name', '', false);
             }
             else {
                return array();
            }
	}

	public function Name() {
		return $this->Name . (($this->Status == 'comingsoon') ? ' (Coming Soon)' : '');
	}

   /**
	 * Return the country name for this store.
	 */
	public function getCountryName() {
		return Geoip::countryCode2name($this->Country);
	}

	/**
	 * Return the link to view this store
	 */
    public function Link($type="map") {

		// Get a single page which uses the StoreLocator page type
		switch($type) {
			case 'map' :
				return DataObject::get_one('StoreLocator')->Link() . '?store=' . $this->ID;
			default :
			case 'details' :
				return DataObject::get_one('StoreLocator')->Link()  . 'show/' . $this->URLSegment;

	    }
	}

	/**
	 * Let the templates know if there is a know location for this store.
	 */
	public function LocationKnown() {
		return ( $this->Lat != 0 || $this->Lng !=0 );
	}

	/**
	 * A function to convert a numeric Lat/Lng to something human readable.
	 */
	public function HumanGeo($axis) {

		// Nothing to do if we don't have geo for this store.
		if ( !$this->LocationKnown() ) return false;

		$string = '';

		// If longitude is required.
		if ($axis == 'lng') {
			$string .= ($this->Lng > 0) ? 'E ' : 'W ';
			$string .= str_replace('.', '&deg; ', trim($this->Lng, '-'));
		}

		// If latitude is required

		if ($axis == 'lat') {
			$string .= ($this->Lat > 0) ? 'N ' : 'S ';
			$string .= str_replace('.', '&deg; ', trim($this->Lat, '-'));
		}

		return $string;
	}

	public function DropdownLabel() {
		return (($this->StateID) ? $this->State()->Name . ' - ' : '') . $this->Name;
	}

	public static function DropdownList() {
		$return = array(''  => 'Select a store...');
		foreach (self::Countries() as $id => $country) {
			$return[$country] = DataObject::get('Store', '"Store"."CountryID" = \'' . $id . "'", '"State"."Name", "Store"."Name"', 'LEFT JOIN "State" ON "Store"."StateID" = "State"."ID"')->toDropdownMap('ID', 'DropdownLabel', '', false);
		}
		return $return;
	}
}