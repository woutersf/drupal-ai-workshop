# AI API

## What is this
Not everyone is a backend developer and not everyone wants to setup a lot of
custom code just to expose the AI modules capabilities.

If you just want a secure, but usable proxy then this is the module for you.

It will make it possible to create an access profile (currently for chat only),
where you can get an OpenAI compatible API or build your own API rules, but that
can interact with any provider or model, that you choose.

Since each access profile is its own config entity, it is shippable inside the
config/install directory, so your own module can have a direct connection to
an provider, but without the hassle of coding it up yourself.

## How to setup your own access profile
1. Install the module.
2. Visit AI Access Profiles (/admin/config/ai/ai-access-profile), via Configuration -> AI
3. Click Add AI Access Profile
4. Give it a label - note that the id it sets is your Access Value.
5. Make sure its enabled.
6. Add a description to know what this profile is used for.
7. Choose Access Method - this is the place where it will look for the Access Value and know that your access profile is the one to use. Keep as is, to copy OpenAI.
8. Choose Access Key - this is the key to use for the Access Method, to check against the Access Value. Keep as is, to copy OpenAI.
9. Choose a Permission - this is the permission required to access your profile. This is so you can link it to your own permission system.
10. Select from the operation types available the pattern to use (Currently just OpenAI Based Chat)
11. You can now select the required provider - you can either keep the default for the site, allow all providers or a select few and let the end user decide.

## How to use your newly added access profile
Take this example, we set our access profile with the id `test_profile` with just OpenAI and gpt-4o-mini.

The call would look exactly like a [Chat Completion](https://platform.openai.com/docs/api-reference/making-requests) call from OpenAI with the added data key `provider`.

So in CURL it would be:

```
curl https://yoursite/v1/chat/completions \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer test_profile" \
  -d '{
     "model": "gpt-4o-mini",
     "provider": "openai",
     "messages": [{"role": "user", "content": "Say this is a test!"}],
   }'
```

Let's do another example - this time access profile with the id `custom_solution` with default provider/model, but with Access Method set to Query String and the Access Key set to `custom_check`.

In CURL it would be:

```
curl https://yoursite/v1/chat/completions?custom_check=custom_solution \
  -H "Content-Type: application/json" \
  -d '{
     "messages": [{"role": "user", "content": "Say this is a test!"}],
   }'
```

## How to ship the access profile with my own module.
The configuration creates a configuration called `ai_api.ai_access_profile.{your-name}`. Just put this in the config/install directory of your module and make sure to make ai_api a requirement.

## How to allow anonymous users to access an endpoint.
By default it doesn't matter if an anonymous user has the permission you have set, they will still be blocked from calling the endpoint. This is to not cause security issues by setting the wrong permission.

You can open this up, but should not do this, unless the whole website is secured behind some security mechanism, because:

1. You open up for anyone to use your website as a proxy at your expense.
2. You open up for anyone to send malicious prompts to get you blocked.

If you still want to open this up the magic keyword is to:

1. Add `$settings['ai_api_permissive_mode'] = TRUE;` in settings.php.
