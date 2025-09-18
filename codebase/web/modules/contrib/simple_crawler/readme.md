
# Simple Crawler

## What is this

Simple Crawler helps you scrape or even crawl webpages and websites for context, research or migrations. It just a wrapper around Guzzle, so it will not be able to scrape client side rendered webpages, but for everything else its great. For scraping with a real browser look into the [ScrapingBot](https://www.drupal.org/project/scrapingbot) module.

Simple Crawler is a module that currently have two things available for it. The one thing is a service where you can get scrape server side rendered webpages using a virutal browser in a service for any third party module that would want to use it for SEO, AI context, research, migrations etc. It has the [readability](https://github.com/mozilla/readability) logic around it, that makes it possible for it to just pick out the article from a body.

The other core feature is that it has one AI Automator type for the AI Automator module that can be found in the [AI module](https://www.drupal.org/project/ai). It makes it possible to take a link and scrape things like the header image, title, full HTML body, article etc. It also offers this but in a depth crawling way, meaning it can go levels deep and crawl an whole website.

For more information on how to use the AI Automator (previously AI Interpolator), check https://workflows-of-ai.com.

Note that this is the follow up module of the AI Interpolator Simple Crawler and makes that module obsolete for Drupal 10.3+.

## Features
* Scrape a website using a simple service.
* Scrape just the article using a simple service.
* Scrape title, image and links from a simple link using the AI Automator.
* Deep scrape whole websites for context or for migrations.

## Requirements
* To use it, you need to use a third party module using the service. Currently its only usable with the AI Automator submodule of the [AI module](https://www.drupal.org/project/ai)

## How to use as AI Automator type
1. Install the AI Automator from the [AI module](https://www.drupal.org/project/ai).
2. Install this module.
3. Create some entity or node type with a link field.
4. Create a formatted text long field.
5. Enable AI Automator checkbox and configure it.
6. Create an entity of the type you generated, fill in some link.
7. The text long field will be filled out with the scraped page.

## How to use the Simple Crawler service.
This is a code example on how you can get only the article for a webpage.

```
$crawler = \Drupal::service('simple_crawler.crawler');
// I want the article only, not the whole raw HTML.
$article_only = TRUE;
// Get answers in a string.
$article_text = $crawler->scrapePageAsBrowser('https://www.drupal.org/project/ai', $article_only);
```

## Sponsors
This module was supported by [FreelyGive](https://freelygive.io/), your partner in Drupal AI.
