# Feed Scorer plugin

This repo contains a (server-side) plugin for the tiny-tiny-RSS reader ([tt-rss](https://tt-rss.org/)) which enables to score each feed based on a regex filter of its title.

# Motivation & Purpose

I have a self-hosted instance of the tt-rss reader which I use to keep up with published literature in my academic field.

Out-of-the-box, the reader can use regex filters to "score" individual articles so that they would be sorted earlier/later in the default feed of all unread articles. If multiple filters "apply" to an article, the scores are summed for the final score value. This allows, for example, to define two separate filters looking for two different keywords, and score all articles which feature both higher than articles which feature only one of them.

However, the built-in system does not allow to assign a score to all feeds based on a regex filter using the feed title as input. 

My concrete use case is that I've added the respective journal impact-factors (IFs) to the feed titles (format: e.g. "[64.8] Nature" and "[11.1] PNAS"). So, I would like to have a single filter which adds a score based on the numeric values in the brackets.

In principle, it is possible to add a filter for each individual feed (i.e. a filter only applied to that one feed) and score all articles with a constant number. However, this is inpractical due to two reasons. First, I have a large number of feeds so this would be tedious and error-prone. 

Second, and more importantly, I will have two conceptually independent sources of scores (keywords and feeds) and for now, I do not know what their relative scoring should be. For example, say I have two keywords "memory" and "learning" (because I am interested in research studying memory and learning) and I want to score articles coming out of Nature (IF: 64.8) more than out of PNAS (IF: 11.1). What should the scores for memory and learning be, relative to the difference in IFs?

The way I plan to figure that out is by trial and error, as I am unsure about any a priori reasoning on this.
One way to conceptualize this problem is that keywords will have one weight and IFs will have another weight in the total score assigned to individual articles. To make my life easy, I want to be able to change the "weighting" factor of all IFs in one go. This way, I can change the relative weighting between keywords and feeds in one single filter.