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

		$host->add_hook($host::HOOK_PREFS_TAB, $this);
		
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
			
			$feed_modifier_scaling  = $this->host->get($this, "feed_modifier_scaling") ?? 1;
			Debug::log("Feed modifier scaling: " . $feed_modifier_scaling);

			$score = $number * $feed_modifier_scaling;
			Debug::log("final article score based on feed-title: " . $score);
			$article["score_modifier"] = $score;

		} else {
			Debug::log("No match found - not going to set feed-based score.");
		}

		return $article;
	}


	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		$feed_modifier_scaling  = $this->host->get($this, "feed_modifier_scaling") ?? 1;
				
		?>
		<div dojoType='dijit.layout.AccordionPane'
			title="<i class='material-icons'>extension</i> <?= __('ModifyScore based on Feed Title settings') ?>">

			<?= format_notice("Set global multiplier for feed title based artcile score.") ?>

			<form dojoType='dijit.form.Form'>

				<?= \Controls\pluginhandler_tags($this, "save") ?>

				<script type="dojo/method" event="onSubmit" args="evt">
					evt.preventDefault();
					if (this.validate()) {
						Notify.progress('Saving data...', true);
						xhr.post("backend.php", this.getValues(), (reply) => {
							Notify.info(reply);
						})
					}
				</script>

				<fieldset>
					<label class='NumberTextBox'>
						<?= \Controls\input_tag("feed_modifier_scaling", $feed_modifier_scaling, 'text', ['dojoType' => 'dijit.form.NumberTextBox']) ?>
						<?= __("Multiply the feed-specific score (in feed title inside brackets, e.g. '[15.3]') with this 'scaling factor' for all feeds.") ?>
					</label>
				</fieldset>

				<hr/>

				<?= \Controls\submit_tag(__("Save")) ?>
			</form>
		</div>
		<?php
	}

	function save() {
		$feed_modifier_scaling = $feed_modifier_scaling = $_POST["feed_modifier_scaling"] ?? 1;

		$this->host->set($this, "feed_modifier_scaling", $feed_modifier_scaling);

		echo __("Data saved.");
	}

	function api_version() {
		return 2;
	}
}