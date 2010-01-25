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
		
		$skript = '
	var map = null;
    var startGeocoded = null;
    var destinationGeocoded = null;
    
    function goMap24(){
      map = Map24.Webservices.getMap24Application({
        AppKey: "'.$applicationcode.'",
        MapArea: document.getElementById( "maparea" ),
        MapWidth: '.$breite.',
        MapHeight: '.$hoehe.'
      });
    }
  
    /*The route is calculated between the passed start and destination*/
    function calculateRouteAddr( start, destination ){
      if( Map24.isNull( start ) ) return;
      if( Map24.isNull( destination ) ) return;
      geocodePoints( start, destination );
      return;
    }
    
    /*Geocode the start and destination address*/
    function geocodePoints( start, destination ){
      //1. Geocode the start address
      geocode( start, onGeocodeStart )
      //2. When start address has been geocoded, then geocode destination address      
      function onGeocodeStart( geoRes ){
        startGeocoded = geoRes.firstResult;
        geocode( destination, onGeocodeDest )
      }
      //3. When both start and dest address have been geocoded, then start route calculation
      function onGeocodeDest( geoRes ){
        destinationGeocoded = geoRes.firstResult;
        calculateRouteCoord( startGeocoded, destinationGeocoded );
      }
    }
      
    //Calculate a route between two addresses
    function calculateRouteCoord( startGeocoded, destinationGeocoded ){
      if( Map24.isNull( startGeocoded ) ) return;
      if( Map24.isNull( destinationGeocoded ) ) return;

      /*Construct the Route Request*/      
      var routeRequest = new Map24.Webservices.Request.CalculateRoute();
      
      routeRequest.Start = new Map24.Webservices.Point();
      routeRequest.Start.Coordinate = new Map24.Coordinate( startGeocoded.Coordinate.Longitude,
                                                            startGeocoded.Coordinate.Latitude );
      routeRequest.Destination = new Map24.Webservices.Point();
      routeRequest.Destination.Coordinate = new Map24.Coordinate( '.$longitude.', '.$latitude.' ); 

      routeRequest.DescriptionLanguage = "DE";
      routeRequest.CalculationMode = document.getElementById("calculation_mode").value;
      routeRequest.VehicleType = "Car";
	  
      //Send the route request to the Map24 Web services
      map.Webservices.sendRequest( routeRequest );
      
    //This listener is called when the route calculation has finished
    map.onCalculateRoute = function( routeResult ){
      var mrcContainer = new Map24.Webservices.Request.MapletRemoteControl();
      
    	/* Push the route onto the map (visualize the route). The route is now a map object that has the ID = currentRoute*/
      mrcContainer.push( 
        new Map24.Webservices.MRC.DeclareMap24RouteObject({
          MapObjectID: "currentRoute",
          Map24RouteID: routeResult.Route.RouteID,
          Color: new Map24.Color( 255, 100, 100, 0 )
        }) 
      );
      
      //Create table with textual description of the route
      var route_table = document.getElementById("route_table");
  	
      route_table.removeChild( document.getElementById("route_table_body") );
      var tbody = document.createElement("TBODY");
      tbody.setAttribute("id", "route_table_body");
      route_table.appendChild(tbody);
      
      var info_row = tbody.appendChild(document.createElement("TR"));
      var info_cell = info_row.appendChild(document.createElement("TD"));
      info_cell.appendChild( document.createTextNode( "Total Length:" )); 
      info_cell = info_row.appendChild(document.createElement("TD"));
      //Output the total length of the route in km
      info_cell.appendChild( document.createTextNode( (routeResult.Route.TotalLength/1000) + " km"));
      
      info_row = tbody.appendChild(document.createElement("TR"));
      info_cell = info_row.appendChild(document.createElement("TD"));
      info_cell.appendChild( document.createTextNode( "Total Time:" )); 
      info_cell = info_row.appendChild(document.createElement("TD"));
      //Output the total time of the route
      info_cell.appendChild( document.createTextNode( (routeResult.Route.TotalTime/(60*60)).toPrecision(3) + " h" ));
  		
      //Iterate through the route segments and output the step-by-step textual description of the route
      for(var i = 0; i < routeResult.Route.Segments.length; i++){
        for(var j = 0; j < routeResult.Route.Segments[i].Descriptions.length; j++){
          routeResult.Route.Segments[i].Descriptions[j].Text = 
          routeResult.Route.Segments[i].Descriptions[j].Text.replace(/(\[|\[\/)[0-9A-Z_]+\]/g, \'\' );
          
          var info_row = tbody.appendChild(document.createElement("TR"));
          var info_cell1 = info_row.appendChild(document.createElement("TD"));
          info_cell1.appendChild( document.createTextNode( (i+1) + ".") );
          var info_cell2 = info_row.appendChild(document.createElement("TD"));
          info_cell2.appendChild( document.createTextNode( routeResult.Route.Segments[i].Descriptions[j].Text) );
        }
      }
  		  
    	/*Center the map view on the visualization of the route. View Percentage determines the
      forced amount of percent that is left between the border of the map view and the route
      object. You can also specify the MinimumWidth that specifies the width of the map view 
      in real-world meters.*/
      mrcContainer.push( genSetMapViewMapObj( { ViewPercentage: 100, MapObjectIDs: "currentRoute" } ) );
          
    	//Enable the visibility of the route 
      mrcContainer.push( genControlMapObject( { Control: "ENABLE", MapObjectIDs: "currentRoute" } ) );
      
      map.Webservices.sendRequest( mrcContainer );
    }
  }
      
    //Geocoding. The onGeoFunc function is called, as soon as the geocoding is finished.
    function geocode( address, onGeoFunc, _params ){
      map.Webservices.sendRequest(
        new Map24.Webservices.Request.MapSearchFree(map, {
          SearchText: address,
          MaxNoOfAlternatives: 100
        })
      );
    
      map.onMapSearchFree = function( geocodingResult ){
        var geoRes = new Object();
        geoRes.Alternatives = geocodingResult.Alternatives;
        geoRes.firstResult = geoRes.Alternatives[0];
         
        if( typeof _params == "undefined" ) {
          onGeoFunc( geoRes );				
        }
        else{
          onGeoFunc( geoRes, _params );
        }
      }
    }    
      
    //Factory functions
    function genSetMapViewMapObj( _params ){
      
      if( Map24.isNull( _params.MinimumWidth ) )  
        _params.MinimumWidth = 0;
       
      if( Map24.isNull( _params.ViewPercentage ) )  
        _params.ViewPercentage = 0;
      
      _params.ClippingWidth = new Map24.Webservices.ClippingWidth( _params);
      
      var SetMapViewMapObj = new Map24.Webservices.MRC.SetMapView( _params );
    
      return SetMapViewMapObj;
    }
    
    function genControlMapObject( _params ){
      var ControlObject = new Map24.Webservices.MRC.ControlMapObject({
        Control: _params.Control,
        MapObjectIDs: _params.MapObjectIDs
      });
      return ControlObject;
    }
	
      function addHTMLObject(id, lon,lat,htmltext){
        var mrcContainer = new Map24.Webservices.Request.MapletRemoteControl( map );
        mrcContainer.push(
          new Map24.Webservices.MRC.DeclareMap24HTMLObject({
            MapObjectID: id,
            Coordinate: new Map24.Coordinate( lon, lat ),
            HTML: htmltext
          })
        );
        mrcContainer.push( genControlMapObject( "ENABLE", id ) );
        map.Webservices.sendRequest( mrcContainer );
      }

            
      function centerOnMapObject( id, clippingWidth ){
        var mrcContainer = new Map24.Webservices.Request.MapletRemoteControl( map );
        mrcContainer.push( 
          SetMapView = new Map24.Webservices.MRC.SetMapView({
            ClippingWidth: new Map24.Webservices.ClippingWidth(
              { MinimumWidth: clippingWidth }
            ),            
            MapObjectIDs: id
          })
        );
        map.Webservices.sendRequest( mrcContainer );
      }	
	  
	  function showMapObject( id ){
        var mrcContainer = new Map24.Webservices.Request.MapletRemoteControl( map );
        mrcContainer.push( genControlMapObject( "ENABLE", id ) );
        map.Webservices.sendRequest( mrcContainer );
      }
	  
	function addLocation(){
        var mrcContainer = new Map24.Webservices.Request.MapletRemoteControl( map );
        mrcContainer.push(
          new Map24.Webservices.MRC.DeclareMap24Location({
            MapObjectID: "myLocation",
            Coordinate: new Map24.Coordinate( '.$longitude.', '.$latitude.' ),
            LogoURL: "http://kletterwald-leipzig.de/uploads/'.$icon.'",
            SymbolID: 20100
          })
        );
        mrcContainer.push(
          new Map24.Webservices.MRC.ControlMapObject({
            Control: "ENABLE",
            MapObjectIDs: "myLocation"
          })
        );
        mrcContainer.push(
          new Map24.Webservices.MRC.SetMapView({
            Coordinates: new Map24.Coordinate( '.$longitude.', '.$latitude.' ),
            ClippingWidth: new Map24.Webservices.ClippingWidth(
              { MinimumWidth: '.$minimumwidth.' }            
            )
          })
        );
        map.Webservices.sendRequest( mrcContainer );
      }	  
	  
		function calculateRoute()
		{
			calculateRouteAddr(document.getElementById("map24_start_address").value, "'.$zieladresse.'"); 
		}
	
		function startMap24Plugin()
		{
			goMap24();
		
/*
			htmltext = "<div style=\"width: 100px; height: 100px; background-color:#ffffff\" id=\"map24_locationtext\">"+  "test" +  "</div>";
			addHTMLObject("map24_location", '.$longitude.', '.$latitude.', htmltext);
	
			centerOnMapObject("map24_location", '.$minimumwidth.');
*/
			addLocation(); 
									
		}
	';

		$Map24_SRC = "http://api.map24.com/ajax/1.2.8";

		$GLOBALS['TSFE']->additionalHeaderData["zn_map24ajax"].=' <script language="JavaScript" type="text/javascript" src="'.$Map24_SRC.'"></script>';
		$GLOBALS["TSFE"]->setJS("zn_map24ajax",$skript); 
		$GLOBALS['TSFE']->JSeventFuncCalls['onload'][]= 'startMap24Plugin();';
		
		$content ="	Start: <input type='text' name='map24_start_address' size='25' id='map24_start_address' value='Ihre Startpunkt'>
				    <input type='hidden' name='map24_dest_address' id='map24_dest_address' value='".$zieladresse."'> Modus: <select id=\"calculation_mode\"><option value=\"Faster\">Schnellste</option><option value=\"Shortest\">K&uuml;rzeste</option></select>
					<a href='javascript:calculateRoute();' id=\"calculation_button\">Berechnen</a>";
		$content.='<div id="maparea"></div><br />
					<table id="route_table">
						<tbody id="route_table_body"></tbody>
					</table>';
	
 		return $this->pi_wrapInBaseClass($content);
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/zn_map24ajax/pi1/class.tx_znmap24ajax_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/zn_map24ajax/pi1/class.tx_znmap24ajax_pi1.php']);
}
?>