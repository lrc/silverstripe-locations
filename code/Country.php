<?php
class LRC_Country extends DataObject
{
	static $singular_name = 'Country';
	static $plural_name = 'Countries';
	
	static $db = array(
		'Name' => 'Varchar(60)',
		'Abbreviation' => 'Varchar(2)'
	);

	static $has_many = array(
		'States' => 'LRC_State',
        'Locations' => 'LRC_Location'
	);

        function getCMSFields() {
		$fields = parent::getCMSFields();

		// Load the store locator script
		Requirements::javascript(MY_SITE_DIR . '/javascript/country-admin.js');
		
		$fields->addFieldToTab('Root.Main', new CountryDropdownField('Country', 'Country', array_merge(Geoip::getCountryDropDown())));

		// Initalise the jQuery script
		// TODO: Listen for the ready event for the AJAX call. The issue is that if we use the standard jQuery ready event
		// it gets called before the fields have completely loaded.
		$fields->addFieldToTab('Root.Main', new LiteralField('loadScript', '<script>initCountryAdmin();</script>'));

		return $fields;
	}

	/**
	 * List job types based on jobs currently available
         *
         * @return DataObjectSet
         */

	function listCountriesAndStates() {
		$countries = new DataObjectSet();

		//$results = DB::query("SELECT c.ID AS CountryId, c.Name AS CountryName, s.ID AS StateID, s.Name AS StateName FROM country c LEFT JOIN state s ON s.CountryID = c.ID ORDER BY c.Name;");
		$results = DB::query("SELECT ID, Name FROM Country ORDER BY Name ASC");

		if($results) {
			foreach($results as $result) {
				$countryId = $result['ID'];
				$countries->push(new ArrayData(array(
				'Country' => array('ID'=>$result['ID'],'Name'=>$result['Name']),
				'States' => DataObject::get("State","CountryId = '$countryId'")
				)));
			}
			return $countries;
		}

		return false;
	}

	/*
	 * Lists countries that have stores
	 *
	 */
	public static function ListStoreCountries()
	{
		// Query
		// SELECT DISTINCT store.CountryId, country.ClassName, country.Created, country.LastEdited, country.Name, country.Abbreviation
		// FROM country
		// JOIN store ON store.CountryId = country.ID

		  $sqlQuery = new SQLQuery();
		  $sqlQuery->select = array(
										'Store.CountryID AS ID',
										'Country.ClassName AS ClassName',
										'Country.Created AS Created',
										'Country.LastEdited AS LastEdited',
										'Country.Name AS Name',
										'Country.Abbreviation AS Abbreviation'
									);

		 $sqlQuery->distinct = true;

		  $sqlQuery->from = array("`Country` JOIN `Store` ON `Store`.`CountryId` = `Country`.`ID`");

		  // get the raw SQL
		  $rawSQL = $sqlQuery->sql();

		  // execute and return a Query-object
		  $result = $sqlQuery->execute();

		  // let Silverstripe work the magic
		  if($result)
		  {
			  return singleton('Country')->buildDataObjectSet($result);
		  }

		  return false;
	}

	/**
	 * Override function so states are returned in alphabetical order.
	 * @return DataObjectSet The states in this country
	 */
	public function States() {
		return DataObject::get('State', 'CountryID = ' . $this->ID, 'Name');
	}

	/**
	 * Get the stores for the join form.
	 */
	public function ValidStores() {
		return DataObject::get('Store', 'CountryID = ' . $this->ID . ' AND EmailAddress IS NOT NULL', 'Name');
	}
}

?>