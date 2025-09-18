
# ConvertAPI

## What is this

[ConvertAPI](https://www.convertapi.com/) is a service that can take an Excel/PDF/Word file and convert it into pure text. It is recommended to use [Unstructured](https://www.drupal.org/project/unstructured) instead since it provides better results and can be self hosted, but if you are really only looking for a really cheap way of turning those files into text without much effort ConvertAPI is easier and cheaper to use if you use the service.

ConvertAPI is a module that currently have two things available for it. The one thing is a service where you can get text from a file from the [ConvertAPI](https://www.convertapi.com/) service for any third party module that would want to use it for contextualize AI calls or show the file data to the end user.

The other core feature is that it has one AI Automator type for the AI Automator module that can be found in the [AI module](https://www.drupal.org/project/ai). It makes it possible to take any file field and fill out any text field. This can work in an Automator workflow/blueprint to make powerful end products.

For more information on how to use the AI Automator (previously AI Interpolator), check https://workflows-of-ai.com.

Note that this is the follow up module of the AI Interpolator ConvertAPI and makes that module obsolete for Drupal 10.3+.

## Features
* Create text representation from PDF files.
* Create text representation from Excel files.
* Create text representation from Word files.
* All without having to install a single applications on your server.
## Requirements
* Requires an account at [ConvertAPI](https://www.convertapi.com/). There is a free trial.
* To use it, you need to use a third party module using the service. Currently its only usable with the AI Automator submodule of the [AI module](https://www.drupal.org/project/ai)
## How to use as AI Automator type
1. Install the [AI module](https://www.drupal.org/project/ai).
2. Install this module.
3. Visit /admin/config/convertapi/settings and add your api key from your ConvertAPI account.
4. Create some entity or node type with a file field. Make it possible to only upload PDF, Excel or Word files.
5. Create a Text Long, either formatted or unformatted.
6. Enable AI Automator checkbox and configure it.
7. Create an entity of the type you generated, upload a file.
8. The text will be filled out.
## How to use the SerpApi service.
This is a code example on how you can get text for a file entity.

```
// Load some file entity.
$file = File::load(1);
$convertapi = \Drupal::service('convertapi.api');
// Get answers in as text.
$text = $convertapi->convertFromFile($file);
```
