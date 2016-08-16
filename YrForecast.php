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
 * 
 * @todo Need a dynamic rain variable so we do not get "0 - 0" mm but just 0mm
 * @todo Add translation matrixes as public variables so that they can be se by caller
 * @todo Imageformat is hardcoded to SVG, maybe give user a way to choose png of size X
 * @todo Maybe add a check so that we have included the yr-copyright variables, they give us the data after all - lets credit them for it.
 */
class YrForecast
{

    private $yrImageUri = 'http://symbol.yr.no/grafikk/sym/svg/';
    private $xmlData;
    private $xmlUri;
    private $xmlFilename = 'varsel.xml';
    private $cacheMethod = 'file';
    private $cacheTTL = 900;
    public $forecastRowdateFormat = 'H:i';  //date used in each forecastrow
    public $dateFormat = 'Y-m-d H:i:s'; //all other dates
    public $headerTemplate;
    public $footerTemplate;
    public $forecastItemGroupHtmlTeplate;
    public $forecastItemHtmlTeplate;

    /**
     * @param type $urlToYrXML
     */
    public function __construct($urlToYrXML)
    {
        $this->xmlUri = rtrim($urlToYrXML, '/');
        $this->checkThatRequiredFunctionsExist();
    }

    /**
     * Initates printing.
     */
    public function printForecast()
    {
        $this->loadXmlData();
        /*
          tabular->time contains _all_ forecasts in ungrouped rows, so to present it
          like we want we will have to group them. There has to be a better way than
          below but i cant think of one right now.
         */
        $forecastItems = [];
        foreach ($this->xmlData->forecast->tabular->time as $forecastItem) {
            $arrayKey = substr($forecastItem->attributes()['from'], 0, 10);
            $forecastItems[$arrayKey][] = $forecastItem;
        }
        echo $this->parseHeaderAndFooterTemplate($this->headerTemplate);
        foreach ($forecastItems as $groupDate => $forecastItems) {
            echo $this->printForecastItemGroup($groupDate, $forecastItems);
        }
        echo $this->parseHeaderAndFooterTemplate($this->footerTemplate);
    }

    /**
     * This prints a group of items, i.e the four rows for "tommorrow" or 24 rows
     * for 2016-07-01 if using hourly forcast
     * @param type $groupDate
     * @param type $forecastItems
     */
    protected function printForecastItemGroup($groupDate, $forecastItems)
    {
        $dateTimeForDay = new DateTime("$groupDate 12:00:00");
        $dayNameTranslationMatrix = [0 => 'Söndag', 1 => 'Måndag', 2 => 'Tisdag', 3 => 'Onsdag', 4 => 'Torsdag', 5 => 'Fredag', 6 => 'Lördag'];

        $templateTags = [
            '{{itemGroup.date}}',
            '{{itemGroup.date.day}}',
            '{{itemGroup.items}}'
        ];
        $templateValues = [
            $groupDate,
            $dayNameTranslationMatrix[$dateTimeForDay->format("w")],
            $this->printForecastItemRows($forecastItems)
        ];

        echo str_replace($templateTags, $templateValues, $this->forecastItemGroupHtmlTeplate);
    }

    /**
     * This prints each indivual forecastrow (the ones with the sun-image and temp and so on)
     * @param type $forecastItems
     * @return type
     */
    protected function printForecastItemRows($forecastItems)
    {

        $returnValue = '';
        foreach ($forecastItems as $forecastItem) {
            $fromDate = new DateTime($forecastItem->attributes()['from']);
            $toDate = new DateTime($forecastItem->attributes()['to']);
            $periodNameMap = [0 => 'Natt', 1 => 'Morgon', 2 => 'Dag', 3 => 'Kveld'];
            $templateTags = [
                '{{item.fromDate}}',
                '{{item.toDate}}',
                '{{item.period.id}}',
                '{{item.period.name}}',
                '{{item.image.src}}',
                '{{item.image.title}}',
                '{{item.temperature.celsius}}',
                '{{item.precipitation.value}}',
                '{{item.precipitation.min}}',
                '{{item.precipitation.max}}',
                '{{item.wind.image.src}}',
                '{{item.wind.direction.degrees}}',
                '{{item.wind.direction.code}}',
                '{{item.wind.direction.name}}',
                '{{item.wind.speed.mps}}',
                '{{item.wind.speed.name}}',
                '{{item.airpressure.unit}}',
                '{{item.airpressure.value}}',
            ];

            $templateValues = [
                $fromDate->format($this->forecastRowdateFormat),
                $toDate->format($this->forecastRowdateFormat),
                $forecastItem->attributes()['period'],
                $periodNameMap[intval($forecastItem->attributes()['period'])],
                $this->yrImageUri . $forecastItem->symbol->attributes()['var'] . '.svg',
                $forecastItem->symbol->attributes()['name'],
                $forecastItem->temperature->attributes()['value'],
                $forecastItem->precipitation->attributes()['value'],
                empty($forecastItem->precipitation->attributes()['minvalue']) ? '0' : $forecastItem->precipitation->attributes()['minvalue'],
                empty($forecastItem->precipitation->attributes()['maxvalue']) ? '0' : $forecastItem->precipitation->attributes()['maxvalue'],
                $this->buildWindimageUri($forecastItem->windSpeed->attributes()['name'], $forecastItem->windDirection->attributes()['deg']),
                $forecastItem->windDirection->attributes()['deg'],
                $forecastItem->windDirection->attributes()['code'],
                $forecastItem->windDirection->attributes()['name'],
                $forecastItem->windSpeed->attributes()['mps'],
                $forecastItem->windSpeed->attributes()['name'],
                $forecastItem->pressure->attributes()['unit'],
                $forecastItem->pressure->attributes()['value'],
            ];
            $returnValue .= str_replace($templateTags, $templateValues, $this->forecastItemHtmlTeplate);
        }
        return $returnValue;
    }

    /**
     * Pulls data from yr.no if we do not have this cached
     */
    protected function loadXmlData()
    {
        $cacheKey = "yr_no_cache_" . md5("{$this->xmlUri}/{$this->xmlFilename}");
        $xmlString = $this->getCachedData($cacheKey);

        if ($xmlString) {
            $this->xmlData = simplexml_load_string($xmlString); //just assume its correct in cache
        } else {
            $xmlString = file_get_contents("{$this->xmlUri}/{$this->xmlFilename}", false, stream_context_create(['http' => ['method' => "GET"]]));
            $this->xmlData = simplexml_load_string($xmlString);

            if ($this->xmlData instanceof SimpleXMLElement) {
                $this->setCachedData($cacheKey, $xmlString);
            } else {
                die("Failed to parse XML-string into a SimpleXMLElement. Raw string given: <pre>$xmlString</pre>");
            }
        }
    }

    /**
     * Replaces valiables in 
     * @param type $templateString
     * @return type
     */
    protected function parseHeaderAndFooterTemplate($templateString)
    {
        $templateTags = [
            '{{location.name}}',
            '{{location.type}}',
            '{{location.country}}',
            '{{location.timezone.id}}',
            '{{location.timezone.utcoffsetMinutes}}',
            '{{location.altitude}}',
            '{{location.latitude}}',
            '{{location.longitude}}',
            '{{credit.link.text}}',
            '{{credit.link.url}}',
            '{{meta.lastupdate}}',
            '{{meta.nextupdate}}',
            '{{sun.rise}}',
            '{{sun.set}}',
        ];

        $sunrise = new DateTime($this->xmlData->sun->attributes()['rise']);
        $sunset = new DateTime($this->xmlData->sun->attributes()['set']);
        $lastUpdate = new DateTime($this->xmlData->meta->lastupdate);
        $nextUpdate = new DateTime($this->xmlData->meta->nextupdate);

        $templateValues = [
            $this->xmlData->location->name,
            $this->xmlData->location->type,
            $this->xmlData->location->country,
            $this->xmlData->location->timezone->attributes()['id'],
            $this->xmlData->location->timezone->attributes()['utcoffsetMinutes'],
            $this->xmlData->location->location->attributes()['altitude'],
            $this->xmlData->location->location->attributes()['latitude'],
            $this->xmlData->location->location->attributes()['longitude'],
            $this->xmlData->credit->link->attributes()['text'],
            $this->xmlData->credit->link->attributes()['url'],
            $lastUpdate->format($this->dateFormat),
            $nextUpdate->format($this->dateFormat),
            $sunrise->format($this->dateFormat),
            $sunset->format($this->dateFormat), //XXX make datetimeObject
        ];
        return str_replace($templateTags, $templateValues, $templateString);
    }

    /**
     * 
     * @param type $doDisplayHourlyForecast
     */
    public function setDisplayHourlyForecast($doDisplayHourlyForecast)
    {
        $this->xmlFilename = (($doDisplayHourlyForecast) ? 'varsel_time_for_time.xml' : 'varsel.xml');
    }

    /**
     * 
     * @param type $cacheKey
     * @return boolean
     */
    private function getCachedData($cacheKey)
    {
        if ($this->cacheMethod == 'file') {
            if (!is_readable(__DIR__ . "/$cacheKey.cache")) {
                return false;
            }
            $cacheData = unserialize(file_get_contents(__DIR__ . "/$cacheKey.cache"));
            if (is_array($cacheData) && (($cacheData['timestamp'] + $this->cacheTTL) > time() )) {
                return $cacheData['data'];
            }
        }
        if ($this->cacheMethod == 'apcu') {
            return apcu_fetch($cacheKey);
        }
        if ($this->cacheMethod == 'apc') {
            return apc_fetch($cacheKey);
        }
        return false;
    }

    /**
     * 
     * @param type $cacheKey
     * @param type $data
     */
    private function setCachedData($cacheKey, $data)
    {
        if ($this->cacheMethod == 'file') {
            $fileData = ['data' => $data, 'timestamp' => time()];
            file_put_contents(__DIR__ . "/$cacheKey.cache", serialize($fileData));
        }
        if ($this->cacheMethod == 'apcu') {
            apcu_store($cacheKey, $data, $this->cacheTTL);
        }
        if ($this->cacheMethod == 'apc') {
            apc_store($cacheKey, $data, $this->cacheTTL);
        }
    }

    /**
     * 
     */
    protected function checkThatRequiredFunctionsExist()
    {
        if (!ini_get('allow_url_fopen')) {
            die('allow_url_fopen is not set to true, this is a must, see <a href="http://php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen">'
                    . 'http://php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen</a>');
        }
        if (!function_exists('simplexml_load_string')) {
            die('simpleXml seem to not be available, this is a must, see <a href="http://php.net/manual/en/book.simplexml.php">'
                    . 'http://php.net/manual/en/book.simplexml.php</a>');
        }
        if (function_exists('apcu_add')) {
            $this->cacheMethod = 'apcu';
        } elseif (function_exists('apc_add')) {
            $this->cacheMethod = 'apc';
        } else {
            $this->cacheMethod = 'file';
            if (!is_writable(__DIR__)) {
                echo '<div style="background-color: #F99; border-radius: 5px; padding: 4px; font-size: 12px; width: 500px; position: absolute; top: 10px; left: 10px;">APCu and APC cache methods are unavailable and i cannot fallback to file-based cache'
                . ' since i cannot write to my own directory. No cache will be used. Do not run like this in production!</div>';
            }
        }
    }

    protected function buildWindimageUri($speedName, $directionInDegree)
    {
        if ($speedName == 'Stille') {
            return 'http://fil.nrk.no/yr/grafikk/vindpiler/32/vindstille.png';
        }
        return 'http://fil.nrk.no/yr/grafikk/vindpiler/32/vindpil.' . $this->calculateWindArrowSpeedGroup($speedName)
                . '.' . $this->calculateWindArrowDirectionGroup($directionInDegree) . '.png';
    }

    protected function calculateWindArrowDirectionGroup($directionInDegrees)
    {
        $directionInDegrees = intval($directionInDegrees);
        echo "<!-- $directionInDegrees -->";
        $windArrowGroup = 0;
        while ($windArrowGroup < 360) {
            if (($directionInDegrees >= $windArrowGroup) && ($directionInDegrees <= ($windArrowGroup + 5))) {
                return str_pad(($windArrowGroup + 5), 3, '0', STR_PAD_LEFT);
            }
            $windArrowGroup += 5;
        }
        return '000';
    }

    protected function calculateWindArrowSpeedGroup($windSpeedName)
    {
        $nameToImagegroupsTranslationMap = ['Flau vind' => '0000',
            'Svak vind' => '0025',
            'Lett bris' => '0050',
            'Laber bris' => '0075',
            'Frisk bris' => '0100',
            'Liten kuling' => '0125',
            'Stiv kuling' => '0150',
            'Sterk kuling' => '0175',
            'Liten storm' => '0225',
            'Full storm' => '0250',
            'Sterk storm' => '0300',
            'Orkan' => '0350'];
        if (array_key_exists("$windSpeedName", $nameToImagegroupsTranslationMap)) {
            return $nameToImagegroupsTranslationMap["$windSpeedName"];
        }
        return $windSpeedName;
    }

}
