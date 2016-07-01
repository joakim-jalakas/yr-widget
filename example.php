<?php

/**
 * @author Jakim Jalakas <joakim@jalakas.com>
 * @copyright (c) 2016, Joakim Jalakas
 * 
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

require_once 'YrForecast.php'; //we need our class
date_default_timezone_set('Europe/Stockholm'); //Set accordingly


/*
 * Creat a parser, give the url to the forecast. This is the url you get when
 * you go to yr.no and look for a place.
 */
$parser = new YrForecast('http://www.yr.no/sted/Sverige/Västra_Götaland/Bjurdammen');


/**
 * if you want hourly forecasts (ie 24 rows per day instead of 4) you can do: 
 */
//$parser->setDisplayHourlyForecast(true); 


/**
 * Set the format of each row in forecast, to get it like yr we use only H, but you 
 * could aswell do 'Y-m-d H:i' iven if it may be a bit pointless.
 */
$parser->forecastRowdateFormat = 'Y-m-d H:i';


/**
 * Now we begin setting up the templates that will define how we are going to print
 * the actual forecast. In order for this to be somewhat "include this file and it will 'just work'" 
 * i have used inline styles below, this is ofc not the preferred way to do it, but i makes it simpler 
 * for end-user. I also did not make it responsive, but you have the variables so just build on this. 
 */


/**
 * This is the template that is printed before we start looping any groups and rows 
 * The following variables are avaliable: 
  {{location.name}}  - The commn name of your forecast location, like 'Bjurdammen'
  {{location.type}} - Type of location, like "Befolket sted"
  {{location.country}} - Country of said location
  {{location.timezone.id}} - Timzeon id of location, ie "Europe/Stockholm"
  {{location.timezone.utcoffsetMinutes}} - offset in mintes from Unversal time
  {{location.altitude}} - Locations altitude in meters
  {{location.latitude}} - LOcations latitude
  {{location.longitude}} - Locations longitude
  {{credit.link.text}} - The text for the YR credit link. USE THIS!
  {{credit.link.url}} - The link to use when you click the above, plase use.
  {{meta.lastupdate}} - Last update of forecast, format is governed by public var $parser->dateFormat
  {{meta.nextupdate}} - Next update of forecast, format is governed by public var $parser->dateFormat
  {{sun.rise}} - datetime of sunrise at location, format is governed by public var $parser->dateFormat
  {{sun.set}} - datetime of sunset at location, format is governed by public var $parser->dateFormat
 */
$parser->headerTemplate = '
            name: {{location.name}} <br>
            type: {{location.type}} <br>
            countr: {{location.country}} <br>
            timezid: {{location.timezone.id}} <br>
            offset: {{location.timezone.utcoffsetMinutes}} <br>
            alt: {{location.altitude}} <br>
            lat: {{location.latitude}} <br>
            long:{{location.longitude}} <br>
            link: {{credit.link.text}} <br>
            link url:{{credit.link.url}} <br>
            lastupd: {{meta.lastupdate}} <br>
            nextupd: {{meta.nextupdate}} <br>
sunrise {{sun.rise}}<br>
sunset   {{sun.set}}<br>
';


/**
 * 
 * The following variables are avaliable: 
  {{itemGroup.date}} - date, like 2016-07-01
  {{itemGroup.date.day}} - Name of day, like Söndag (@todo translate from swedish to noweigain)
  {{itemGroup.items}} -  all the items for this date, like they are defined in $parser->forecastItemHtmlTeplate below
 */
$parser->forecastItemGroupHtmlTeplate = '<h1>{{itemGroup.date}} {{itemGroup.date.day}}</h1> <table border="1">{{itemGroup.items}}</table>';


/*
 * This is each single "row" in the forecast, the one that actually shows the forecast data. 
 * The following paramaters are avalable:
  {{item.fromDate}} - startdate for this item, usally we use only time, like 18
  {{item.toDate}} - enddate for this item, usally we use only time, like 18
  {{item.period.id}} - id for the "period", these are  0='Natt', 1='Morgon', 2='Dag', 3='Kveld'];
  {{item.period.name}} - name from the above map, like Kveld
  {{item.image.src}} - forecastImage (the one with the sun ore the rain) source
  {{item.image.title}} - forecastImage title (something like "regn")
  {{item.temperature.celsius}} - Temperature yr think we will get
  {{item.precipitation.value}} - Rain yt thinks we will get
  {{item.precipitation.min}} - Rain yr think we will get, at minimum
  {{item.precipitation.max}} - Rain yr think we will get, at most
  {{item.wind.direction.degrees}} - Wind direction in degree
  {{item.wind.direction.code}} - Wind direction, degrees
  {{item.wind.direction.name}} - Wind direction, like SSE
  {{item.wind.speed.mps}} - wind speed meter per second
  {{item.wind.speed.name}} - wind speed as name, like "Lett Bris"
  {{item.airpressure.unit}} - Aipressure unit.
  {{item.airpressure.value}} - You guessed it! Airpressure value
 */
$parser->forecastItemHtmlTeplate = '<tr><td>{{item.fromDate}} till {{item.toDate}} ({{item.period.id}} {{item.period.name}})</td> '
        . '<td><img src="{{item.image.src}}" title="{{item.image.title}}" /> </td>'
        . '<td>{{item.temperature.celsius}}C </td>'
        . '<td>{{item.precipitation.value}}, min:{{item.precipitation.min}}, max: {{item.precipitation.max}}</td>'
        . '<td>Deg {{item.wind.direction.degrees}} code {{item.wind.direction.code}} name{{item.wind.direction.name}} mps: {{item.wind.speed.mps}} name:{{item.wind.speed.name}}</td>'
        . '<td>{{item.airpressure.value}} {{item.airpressure.unit}}</td>'
        . '</tr>';


/**
 * Template that wraps it all up, the last thing written. 
 * See headerTemplate for variables since this has the same set
 */
$parser->footerTemplate = '';


/**
 * This actually prints the forecast
 */
$parser->printForecast();
