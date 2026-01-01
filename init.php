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
		
		$score_title = self::extract_score_from_title($feed_title);

		Debug::log("final article score based on feed-title: " . $score);
		$article["score_modifier"] = $score_title;

		return $article;
	}


	function hook_prefs_tab($args) {
		if ($args != "prefFeeds") return;

		$feed_modifier_scaling  = $this->host->get($this, "feed_modifier_scaling") ?? 1;
		$update_feeds_scores = false; // always start with "no update"
				
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

				<fieldset>
					<label class='checkbox'>
						<?= \Controls\checkbox_tag("update_feeds_scores", $update_feeds_scores) ?>
						<?= __("Update all Scores of all feeds (this may take a while)") ?>
					</label>
				</fieldset>

				<hr/>

				<?= \Controls\submit_tag(__("Save")) ?>
			</form>
		</div>
		<?php
	}

	function save() {
		$update_feeds_scores = $_POST["update_feeds_scores"] ?? false;

		$feed_modifier_scaling = $_POST["feed_modifier_scaling"] ?? 1;
		$this->host->set($this, "feed_modifier_scaling", $feed_modifier_scaling);

		if ($update_feeds_scores){

			$owner_id = $_SESSION['uid'];

			$feeds = ORM::for_table('ttrss_feeds')
    			->where('owner_uid',$owner_id)
				->select("title")
				->select("id")
    			->find_many();

			if (!empty($feeds)) { // TODO: assert this works to skip the below code if no feeds are present..
				
				foreach ($feeds as $feed){
				
					$feed_title = $feed->title;
					$feed_id = $feed->id;


					$score_title = self::extract_score_from_title($feed_title);

					$filters = RSSUtils::load_filters($feed_id,$owner_id);

					$matched_filters = [];
					
					foreach ($filters as $filter){
						$match = false;
						foreach ($filter["actions"] as $action) {
							if ($action["type"] == "score"){
								$match = true; 
								break;
							}
						}

						if ($match){
							$matched_filters[] = $filter;
						}
					}
					
					$article_ref_ids = ORM::for_table('ttrss_user_entries')
						->where('owner_uid',$owner_id)
						->where('feed_id',$feed_id)
						->select("ref_id")
						->find_many();

					foreach ($article_ref_ids as $ref_id_entry){
					 	$ref_id = $ref_id_entry->ref_id;
						$article = ORM::for_table('ttrss_entries')
						->where('id',$ref_id)
						->select('title')
						->select('content')
						->select('link')
						->select('author')	
						->find_one();
						
						$title = $article->title;
						$content = $article->content;
						$link = $article->link;
						$author = $article->author;
								
						$tag_entry = ORM::for_table('ttrss_user_entries')
						->where('owner_uid',$owner_id)
						->where('feed_id',$feed_id)
						->where("ref_id",$ref_id)
						->select("tag_cache")
						->select("score")
						->find_many();
						$tags = $tag_entry->tag_cache ?? [];
						$old_score = $tag_entry->score;
						Debug::log("old score was: " . $old_score);


						// reapply all filters which (also) modify the score
						$filter_actions = RSSUtils::eval_article_filters($matched_filters, $title, $content, $link, $author, $tags);
						$score_article = RSSUtils::calculate_article_score($filter_actions);
						
						$score = round($score_article + $score_title);
						
						$stmt = $this->pdo->prepare('
							UPDATE ttrss_user_entries
							SET score = :score
							WHERE ref_id = :ref_id
							AND owner_uid = :owner_id
							AND feed_id = :feed_id
							');

						$stmt->execute([
							":score" => $score,
							":ref_id" => $ref_id,
							":feed_id" => $feed_id,
							":owner_id" => $owner_id
						]);
					}
				}
			}
		}

		$this->host->set($this, "feed_modifier_scaling", $feed_modifier_scaling);

		echo __("Data saved.");
	}

	private function extract_score_from_title($feed_title){
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
			$score = 0;
		}
		return $score;
	}

	function api_version() {
		return 2;
	}
}