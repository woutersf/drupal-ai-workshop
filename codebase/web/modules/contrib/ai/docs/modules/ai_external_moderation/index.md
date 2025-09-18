# AI External Moderation
## What is the AI External Moderation module?
The AI External Moderation module intercepts input sent to an LLM by other
AI Core-based modules and sends it to a configured Moderation provider before it
is run. If the LLM indicates that the input is unsafe, the operation will be
prevented from completing. **If you are using only the OpenAI LLM, you will not
need to enable this module** as all calls using this provider are automatically
moderated before being sent.

## Dependencies
This module requires the AI Core module to be enabled and configured with at
least one AI Provider enabled and installed that provides a moderation
operation. For more information on AI Providers and Operation Types, please see
[the documentation](https://project.pages.drupalcode.org/ai/developers/base_calls/).

## How to configure the AI External Moderation module
To configure the module:
1. Visit /admin/config/ai/ai_external_moderation
2. Select your chosen provider from the list. If you want to configure multiple
   providers, you will be able to add more once the form has been saved.
3. Select your chosen model from the list. If you have multiple models, you can
   press the "Add another model" but to select it: multiple models will be run
   in the order they have been added to the form.
4. If you only want the moderation to be performed for specific options, you can
   enter their tags. By default, all calls are tagged with the operation type -
   chat, moderation, etc - but some modules provide more specific tags.
5. Save the form.

Once the configuration has been set, any input sent to an LLM through the AI
Core module (with the chosen tags, if using) will automatically be sent for
moderation first. 