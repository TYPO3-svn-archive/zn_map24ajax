<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Axel Klarmann <klarmann@zwo-null.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(PATH_tslib.'class.tslib_pibase.php');


/**
 * Plugin 'Map24 Ajax Router' for the 'zn_map24ajax' extension.
 *
 * @author	Axel Klarmann <klarmann@zwo-null.de>
 * @package	TYPO3
 * @subpackage	tx_znmap24ajax
 */
class tx_znmap24ajax_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_znmap24ajax_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_znmap24ajax_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'zn_map24ajax';	// The extension key.
	var $pi_checkCHash = true;
	
	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content,$conf)	{
		$this->conf=$conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
//		$this->pi_USER_INT_obj=1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!
		
//		$this->no_cache = 1;

		$this->pi_initPIflexForm();
		$what_to_display	= array_flip(explode(",", $this->pi_getFFvalue($this->cObj->data['pi_flexform'], "what_to_display", "sDEF")));

		$breite 	= $this->pi_getFFvalue($this->cObj->data['pi_flexform'], "breite", "sDEF");
		$hoehe	 	= $this->pi_getFFvalue($this->cObj->data['pi_flexform'], "hoehe", "sDEF");
		
		$zieladresse= $this->pi_getFFvalue($this->cObj->data['pi_flexform'], "zieladresse", "sDEF");
		
		$longitude 	= $this->pi_getFFvalue($this->cObj->data['pi_flexform'], "longitude", "sDEF");
		$latitude 	= $this->pi_getFFvalue($this->cObj->data['pi_flexform'], "latitude", "sDEF");

		$icon	 	= $this->pi_getFFvalue($this->cObj->data['pi_flexform'], "icon", "sDEF");
		$titel	 	= $this->pi_getFFvalue($this->cObj->data['pi_flexform'], "titel", "sDEF");
		$text	 	= str_replace(array('"',"'","\n"), array('\"',"\'",""), $this->pi_getFFvalue($this->cObj->data['pi_flexform'], "text", "sDEF"));

		$applicationcode	= $this->pi_getFFvalue($this->cObj->data['pi_flexform'], "applicationcode", "sDEF");
		$minimumwidth		= $this->pi_getFFvalue($this->cObj->data['pi_flexform'], "minimumwidth", "sDEF");

		$displayMode		= $this->pi_getFFvalue($this->cObj->data['pi_flexform'], "displaymode", "sDEF");

		if($conf["useConf"]== 1)
		{
			$what_to_display	= $conf["what_to_display"];
			$breite 			= $conf["breite"];
			$hoehe	 			= $conf["hoehe"];
			$zieladresse		= $conf["zieladresse"];
			$icon	 			= $conf["icon"];
			$titel	 			= $conf["titel"];
			$applicationcode	= $conf["applicationcode"];
			$minimumwidth		= $conf["minimumwidth"];

			$displayMode		= $conf["displaymode"];

		};
		
		$skript = '
			var map = null;
			var startGeocoded = null;
			var destinationGeocoded = null;
			var router = null;
			var routePoints = [];
			var routeID = null;
			
			var myLocation = null;
				
			function goMap24() {
				Map24.loadApi( ["core_api", "wrapper_api"] , map24ApiLoaded );
			}
			
			function map24ApiLoaded(){
				//Initialize mapping client
				 Map24.MapApplication.init( { NodeName: "maparea" } );
				 /* Map24.MapApplication.Map.addListener( "Map24.Event.MapViewChanged", mapViewChanged ); */
				 Map24.MapApplication.Map.addListener( "Map24.Event.MapClientReady", mapClientReady );

				// add static Icon
				addLocation();
			}

			function mapViewChanged( e ){

				var content = "<br /><center><b>The map view changed:</b></center><hr />"+
				"TopLeft, Longitude: "+ e.ClipRect.TopLeft.Longitude+"<br />TopLeft, Latitude: " +e.ClipRect.TopLeft.Latitude+"<br />"+
				"LowerRight, Longitude: "+ e.ClipRect.LowerRight.Longitude +"<br />LowerRight, Latitude: " +e.ClipRect.LowerRight.Latitude+"<br />";
				
				/* alert(content); */
			}			

			function mapClientReady( e ){
				if( e.MapClient.Class == "Applet") {
					Map24.MapApplication.Map.show( "Applet", "c" );
					// add applet Icon
					addLocation();
				};
			}
	  ';
	  
	  	// Script to set location and icon for the given POI
		$skript .=     '
	
				function addLocation(){ 			
		';
		if(($longitude == "") || ($latitude=="")) {
			// Calculating the start location by the given address
			$skript .=     ' 
			//1. Geocode the start address
			var geocoder = new Map24.GeocoderServiceStub();
			geocoder.geocode( { SearchText: Map24.trim("'.$zieladresse.'"), MaxNoOfAlternatives: 10, CallbackFunction: onGeocodeStart } );
	
			//2. When start address has been geocoded, then geocode destination address      
			function onGeocodeStart( geoRes ){
				myLocation = new Map24.Location({
				  Longitude: geoRes[0].getLongitude(),
				  Latitude: geoRes[0].getLatitude(),
				  Description: "'.$titel.'",				  
				  LogoURL: "http://'.$_SERVER["HTTP_HOST"].'/uploads/'.$icon.'"
				});
				myLocation.commit();
				myLocation.show(); 
				myLocation.center( { MinimumWidth: '.$minimumwidth.' } );  

			}';
			
		} else {
			// Start location by direct setting of longitude/latitude
		   $skript.= '
				myLocation = new Map24.Location({
				  Longitude: '.$longitude.',
				  Latitude: '.$latitude.',
				  Description: "'.$titel.'",				  
				  LogoURL: "http://'.$_SERVER["HTTP_HOST"].'/uploads/'.$icon.'"
				});
				myLocation.commit();
				myLocation.show();
				myLocation.center( { MinimumWidth: '.$minimumwidth.' } );		
			';
		};
		$skript .= ' 
			}	  
		
		';	


		// Script for calculating the route
		$skript .= '
  		  
			function startRouting(){
				//Retrieve start and destination of the route from the input fields
				var start = Map24.trim( document.getElementById("map24_start_address").value );
				var destination = Map24.trim( "'.$zieladresse.'" );

				if( start == "" ) { alert("Bitte geben Sie eine Startadresse an!"); return; }
				  
				//Create a geocoder stub
				var geocoder = new Map24.GeocoderServiceStub();
				
				//Geocode the start point of the route
				geocoder.geocode({ 
				  SearchText: start, 
				  //Define the name of the callback function that is called when the result is available on the client.
				  CallbackFunction: setRouteEndPoint, 
				  //Set a parameter that is passed to the callback function. The parameter defines that this is the start point.
				  CallbackParameters: {position: "start"}
				});
				
				//Geocode the destination point of the route
				geocoder.geocode({
				  SearchText: destination,  
				  CallbackFunction: setRouteEndPoint,
				  CallbackParameters: {position: "destination"}
				});
			}
			
			//Callback function that is called when the geocoding result is available.
			//The locations parameter contains an array with multiple alternative geocoding results.
			//The params parameter passes the value of CallbackParameters that specifies which route 
			//end point is returned (start or destination point).
			function setRouteEndPoint(locations, params){
	
				//Access the geocoded address and add it to the routePoints array.
				//The geocoded address is stored at the first position in the locations array.
				routePoints[ params.position ] = locations[0];
				
				//After both the start and the destination addresses are geocoded, this function calls the calculateRoute() function.
				if( typeof routePoints["start"] != "undefined" && typeof routePoints["destination"] != "undefined")
				  calculateRoute(); 
			}
			
			//Calculate the route.
			function calculateRoute() {
			router = new Map24.RoutingServiceStub();
			router.calculateRoute({
			  Start: routePoints["start"],
			  Destination: routePoints["destination"],
			  CallbackFunction: displayRoute,
			  ShowRoute: false,
			  DescriptionLanguage: "de",
			  CalculationMode: document.getElementById("calculation_mode").value
			});
			document.getElementById("print").disabled = true;
			routePoints = [];
			}
			
			//Callback function used to access the calculated route of type Map24.WebServices.Route.
			//This function is called after the client has received the result from the routing service.
			function displayRoute( route ){
			
			//Remember the routeId. It is used e.g. to hide the route.
			routeID = route.RouteID;
			router.showRoute( {
			  RouteId: routeID,
			  Color: [\'#00F\', 150]
			});
			
			//Access the assumed time needed for traversing the route in hours
			var totalTime = ((route.TotalTime)/(60*60) ).toPrecision(3) 
			//Access the total lenght of the route in kilometers
			var totalLength = (route.TotalLength/1000) 
			//Create table with description of the route
			var div_content = "Gesamtzeit: " + totalTime + " h<br>" ;     
			div_content += "Gesamtl&auml;nge: "+ totalLength +" km<br>";
			div_content += "<br>";
			
			//Iterate through the route segments and output the step-by-step textual description of the route
			for(var i = 0; i < route.Segments.length; i++){
			  for(var j = 0; j < route.Segments[i].Descriptions.length; j++){
				//The route description contains tags for further evaluation. For example, the [M24_STREET] tag is used 
				//to denote a street in the description. Add the following line of code to replace these tags by a blank:
				div_content += (i+1) + ". " + route.Segments[i].Descriptions[j].Text.replace(/(\[|\[\/)[0-9A-Z_]+\]/g, "" ) + "<br>";
			  }
			}
			document.getElementById("routeDescription").innerHTML = div_content;
			}
		';

		$Map24_SRC = "http://api.maptp.map24.com/ajax?appkey=".$applicationcode;

		if(($displayMode == "embedHidden") && ($this->piVars["showMap"] != "1")) {
			$content = $this->pi_linkTP($this->pi_getLL('clickHereToOpenRouter'), array($this->prefixId."[showMap]" => 1));
		} else {
			$GLOBALS['TSFE']->additionalHeaderData['zn_map24ajax'].=' <script language="JavaScript" type="text/javascript" src="'.$Map24_SRC.'"></script>';
			$GLOBALS["TSFE"]->setJS('zn_map24ajax',$skript); 
			$GLOBALS['TSFE']->JSeventFuncCalls['onload'][]= 'goMap24();';
			
			$content =  $this->pi_getLL('routingAddress') .
						'<input type="text" name="map24_start_address" size="25" id="map24_start_address" value="' . $this->pi_getLL('startingPoint') . '">' .
						'<input type="hidden" name="map24_dest_address" id="map24_dest_address" value="'.$zieladresse.'">'. $this->pi_getLL('routingMode') . 
						'<select id="calculation_mode"><option value="fast">'. $this->pi_getLL('routingMode_fastest') . '</option><option value="short">' . $this->pi_getLL('routingMode_shortest') . '</option></select>'.
						'<a href="javascript:startRouting();" id="calculation_button">' . $this->pi_getLL('calculateRoute') . '</a>';
			$content.='<div style="width: '.$breite.'px; height: '.$hoehe.'px" id="maparea">&nbsp;</div><br /><div id="routeDescription"></div>';
		};	
 		return $this->pi_wrapInBaseClass($content);
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/zn_map24ajax/pi1/class.tx_znmap24ajax_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/zn_map24ajax/pi1/class.tx_znmap24ajax_pi1.php']);
}
?>