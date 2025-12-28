<?php

class ModifyScore_Filter extends Plugin {

	/** @var PluginHost $host */
	private $host;

	const ACTION_FILTER_FEED_TITLE = "action_filter_feed_title";
	
	function about() {
		return [0.1,
			"Modify Article Score based on Feed title",
			"Peter Vavra"];
	}

	function init($host) {
		$this->host = $host;

		$this->host->add_filter_action($this, self::ACTION_FILTER_FEED_TITLE, __("Set score based on Feed title"));
	}

	function hook_article_filter_action($article, $action) {
		$feed_title = Feeds::_get_title($article["feed"]["id"], $article["owner_uid"]);
		Debug::log('title of current feed is:' . $feed_title);
		
		// Define the regular expression pattern
		$pattern = '/\[(\d+(\.\d+)?)\]/'; # number between brackets, can be int or float

		// Use preg_match to extract the number
		if (preg_match($pattern, $feed_title, $matches)) {
			$number = $matches[1];
			Debug::log("Extracted number: " . $number);
			
			// TODO: add scaling factor, which can be set via new preferences tab
			$article["score_modifier"] = $number;
		} else {
			Debug::log("No match found.");
		}

		return $article;
	}

	function api_version() {
		return 2;
	}
}