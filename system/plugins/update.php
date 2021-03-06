<?php
// Copyright (c) 2013-2016 Datenstrom, http://datenstrom.se
// This file may be used and distributed under the terms of the public license.

// Update plugin
class YellowUpdate
{
	const Version = "0.6.1";
	var $yellow;					//access to API
	
	// Handle initialisation
	function onLoad($yellow)
	{
		$this->yellow = $yellow;
		$this->yellow->config->setDefault("updatePluginsUrl", "https://github.com/datenstrom/yellow-plugins");
		$this->yellow->config->setDefault("updateThemesUrl", "https://github.com/datenstrom/yellow-themes");
		$this->yellow->config->setDefault("updateVersionFile", "version.ini");
		$this->yellow->config->setDefault("updateFile", "update.ini");
	}
	
	// Handle request
	function onRequest($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if($this->isInstallation())
		{
			$statusCode = $this->processRequestInstallation($serverScheme, $serverName, $base, $location, $fileName);
		} else {
			$statusCode = $this->processRequestUpdate($serverScheme, $serverName, $base, $location, $fileName);
		}
		return $statusCode;
	}
	
	// Handle command
	function onCommand($args)
	{
		list($command) = $args;
		switch($command)
		{
			case "update":	$statusCode = $this->updateCommand($args); break;
			default:		$statusCode = 0;
		}
		return $statusCode;
	}
	
	// Handle command help
	function onCommandHelp()
	{
		return "update [FEATURE]";
	}
	
	// Update plugins and themes
	function updateCommand($args)
	{
		$statusCode = 0;
		list($command, $feature) = $args;
		list($statusCode, $dataCurrent) = $this->getSoftwareVersion();
		list($statusCode, $dataLatest) = $this->getSoftwareVersion(true);
		foreach($dataCurrent as $key=>$value)
		{
			if(strnatcasecmp($dataCurrent[$key], $dataLatest[$key]) < 0)
			{
				if(empty($feature) || preg_match("/$feature/i", $key)) ++$updates;
			}
		}
		if($statusCode != 200) echo "ERROR checking updates: $data[error]\n";
		if($updates)
		{
			echo "Yellow $command: $updates update".($updates==1 ? "":"s")." available\n";
		} else {
			echo "Yellow $command: No updates available\n";
			
		}
		return $statusCode;
	}
	
	// Process request to update software
	function processRequestUpdate($serverScheme, $serverName, $base, $location, $fileName)
	{
		return 0;
	}
	
	// Process request to install website
	function processRequestInstallation($serverScheme, $serverName, $base, $location, $fileName)
	{
		$statusCode = 0;
		if(!$this->yellow->isStaticFile($location, $fileName, false))
		{
			$fileName = $this->yellow->lookup->findFileNew($fileName,
				$this->yellow->config->get("webinterfaceNewFile"), $this->yellow->config->get("configDir"), "installation");
			$this->yellow->pages->pages["root/"] = array();
			$this->yellow->page = new YellowPage($this->yellow);
			$this->yellow->page->setRequestInformation($serverScheme, $serverName, $base, $location, $fileName);
			$this->yellow->page->parseData($this->getRawDataInstallation($fileName, $this->yellow->getRequestLanguage()), false, 404);
			$this->yellow->page->parserSafeMode = false;
			$this->yellow->page->parseContent();
			$name = trim(preg_replace("/[^\pL\d\-\. ]/u", "-", $_REQUEST["name"]));
			$email = trim($_REQUEST["email"]);
			$password = trim($_REQUEST["password"]);
			$language = trim($_REQUEST["language"]);
			$status = trim($_REQUEST["status"]);
			if($status == "install")
			{
				$status = "ok";
				$fileNameHome = $this->yellow->lookup->findFileFromLocation("/");
				$fileData = strreplaceu("\r\n", "\n", $this->yellow->toolbox->readFile($fileNameHome));
				if($fileData==$this->getRawDataHome("en") && $language!="en")
				{
					$status = $this->yellow->toolbox->createFile($fileNameHome, $this->getRawDataHome($language)) ? "ok" : "error";
					if($status == "error") $this->yellow->page->error(500, "Can't write file '$fileNameHome'!");
				}
			}
			if($status == "ok")
			{
				if(!empty($email) && !empty($password) && $this->yellow->plugins->isExisting("webinterface"))
				{
					$fileNameUser = $this->yellow->config->get("configDir").$this->yellow->config->get("webinterfaceUserFile");
					$status = $this->yellow->plugins->get("webinterface")->users->update($fileNameUser, $email, $password, $name, $language) ? "ok" : "error";
					if($status == "error") $this->yellow->page->error(500, "Can't write file '$fileNameUser'!");
				}
			}
			if($status == "ok")
			{
				if($this->yellow->config->get("sitename") == "Yellow") $_REQUEST["sitename"] = $name;
				$fileNameConfig = $this->yellow->config->get("configDir").$this->yellow->config->get("configFile");
				$status = $this->yellow->config->update($fileNameConfig, $this->getConfigData()) ? "done" : "error";
				if($status == "error") $this->yellow->page->error(500, "Can't write file '$fileNameConfig'!");
			}
			if($status == "done")
			{
				$statusCode = 303;
				$location = $this->yellow->lookup->normaliseUrl($serverScheme, $serverName, $base, $location);
				$this->yellow->sendStatus($statusCode, $location);
			} else {
				$statusCode = $this->yellow->sendPage();
			}
		}
		return $statusCode;
	}
	
	// Return raw data for installation page
	function getRawDataInstallation($fileName, $language)
	{
		$rawData = $this->yellow->toolbox->readFile($fileName);
		if(empty($rawData))
		{
			$this->yellow->text->setLanguage($language);
			$rawData = "---\nTitle:".$this->yellow->text->get("webinterfaceInstallationTitle")."\nLanguage:$language\nNavigation:navigation\n---\n";
			$rawData .= "<form class=\"installation-form\" action=\"".$this->yellow->page->getLocation(true)."\" method=\"post\">\n";
			$rawData .= "<p><label for=\"name\">".$this->yellow->text->get("webinterfaceSignupName")."</label><br /><input class=\"form-control\" type=\"text\" maxlength=\"64\" name=\"name\" id=\"name\" value=\"\"></p>\n";
			$rawData .= "<p><label for=\"email\">".$this->yellow->text->get("webinterfaceSignupEmail")."</label><br /><input class=\"form-control\" type=\"text\" maxlength=\"64\" name=\"email\" id=\"email\" value=\"\"></p>\n";
			$rawData .= "<p><label for=\"password\">".$this->yellow->text->get("webinterfaceSignupPassword")."</label><br /><input class=\"form-control\" type=\"password\" maxlength=\"64\" name=\"password\" id=\"password\" value=\"\"></p>\n";
			if(count($this->yellow->text->getLanguages()) > 1)
			{
				$rawData .= "<p>";
				foreach($this->yellow->text->getLanguages() as $language)
				{
					$checked = $language==$this->yellow->text->language ? " checked=\"checked\"" : "";
					$rawData .= "<label for=\"$language\"><input type=\"radio\" name=\"language\" id=\"$language\" value=\"$language\"$checked> ".$this->yellow->text->getTextHtml("languageDescription", $language)."</label><br />";
				}
				$rawData .= "</p>\n";
			}
			$rawData .= "<input class=\"btn\" type=\"submit\" value=\"".$this->yellow->text->get("webinterfaceOkButton")."\" />\n";
			$rawData .= "<input type=\"hidden\" name=\"status\" value=\"install\" />\n";
			$rawData .= "</form>\n";
		}
		return $rawData;
	}
	
	// Return raw data for home page
	function getRawDataHome($language)
	{
		$rawData = "---\nTitle: Home\n---\n".strreplaceu("\\n", "\n", $this->yellow->text->getText("webinterfaceInstallationHomePage", $language));
		return $rawData;
	}
	
	// Return configuration data
	function getConfigData()
	{
		$data = array();
		foreach($_REQUEST as $key=>$value)
		{
			if(!$this->yellow->config->isExisting($key)) continue;
			$data[$key] = trim($value);
		}
		$data["# serverScheme"] = $this->yellow->toolbox->getServerScheme();
		$data["# serverName"] = $this->yellow->toolbox->getServerName();
		$data["# serverBase"] = $this->yellow->toolbox->getServerBase();
		$data["# serverTime"] = $this->yellow->toolbox->getServerTime();
		$data["installationMode"] = "0";
		return $data;
	}
	
	// Return software version
	function getSoftwareVersion($latest = false)
	{
		$data = array();
		if($latest)
		{
			list($statusCodePlugins, $dataPlugins) = $this->getSoftwareVersionFromUrl($this->yellow->config->get("updatePluginsUrl"));
			list($statusCodeThemes, $dataThemes) = $this->getSoftwareVersionFromUrl($this->yellow->config->get("updateThemesUrl"));
			$statusCode = max($statusCodePlugins, $statusCodeThemes);
			$data = array_merge($dataPlugins, $dataThemes);
		} else {
			$statusCode = 200;
			foreach($this->yellow->plugins->getData() as $key=>$value) $data[$key] = $value;
			foreach($this->yellow->themes->getData() as $key=>$value) $data[$key] = $value;
		}
		return array($statusCode, $data);
	}
	
	// Return software version from URL
	function getSoftwareVersionFromUrl($url)
	{
		$data = array();
		$urlVersion = $url;
		if(preg_match("#^https://github.com/(.+)$#", $url, $matches))
		{
			$urlVersion = "https://raw.githubusercontent.com/".$matches[1]."/master/".$this->yellow->config->get("updateVersionFile");
		}
		if(extension_loaded("curl"))
		{
			$curlHandle = curl_init();
			curl_setopt($curlHandle, CURLOPT_URL, $urlVersion);
			curl_setopt($curlHandle, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; YellowCore/".YellowCore::Version).")";
			curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 30);
			$rawData = curl_exec($curlHandle);
			$statusCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
			curl_close($curlHandle);
			if($statusCode == 200)
			{
				if(defined("DEBUG") && DEBUG>=2) echo "YellowUpdate::getSoftwareVersion location:$urlVersion\n";
				foreach($this->yellow->toolbox->getTextLines($rawData) as $line)
				{
					preg_match("/^\s*(.*?)\s*:\s*(.*?)\s*$/", $line, $matches);
					if(!empty($matches[1]) && !empty($matches[2]))
					{
						list($version, $url) = explode(',', $matches[2]);
						$data[$matches[1]] = $version;
						if(defined("DEBUG") && DEBUG>=3) echo "YellowUpdate::getSoftwareVersion $matches[1]:$version\n";
					}
				}
			}
			if($statusCode == 0) $statusCode = 444;
			$data["error"] = "$url - ".$this->yellow->toolbox->getHttpStatusFormatted($statusCode);
		} else {
			$statusCode = 500;
			$data["error"] = "Plugin 'update' requires cURL library!";
		}
		return array($statusCode, $data);
	}
	
	// Return if installation is necessary
	function isInstallation()
	{
		return PHP_SAPI!="cli" && $this->yellow->config->get("installationMode");
	}
}
	
$yellow->plugins->register("update", "YellowUpdate", YellowUpdate::Version);
?>