
# 🤖 Drupal & AI - GETTING STARTED Workshop

## AI for editors / AI search / Automated content input

Welcome to the **Drupal & AI - GETTING STARTED Workshop**! This workshop is designed to provide you with hands-on experience integrating and leveraging Artificial Intelligence within the Drupal content management system.

### 🎯 Workshop Goal

The goal of this workshop is to get you started with practical AI implementation in Drupal, covering key areas such as enhancing the editorial experience, implementing AI-powered search (RAG), and setting up automated  AI flows.

---

### Prerequisites

To participate in this workshop, you will need:

* A **GitHub user account** (required to access the Codespace environment).
* A stable internet connection.

---

### Start Your Engines (Starting the Workspace)



1.  Navigate to **https://github.com/codespaces/new**. 
2. Start a new codespace
![Screenshot of the configuration to start the codespace](assets/codespace.png)
3.  Choose the repository **woutersf/drupal-ai-workshop**. 
4. Give it some time as it will pull the images and start the containers.

![Screenshot of the configuration to start the codespace](assets/codespace2.png)

4. Open the Drupal environment by right clicking the url behind port 80 and Clicking Open in Browser

![Screenshot of the open in browser](assets/open_in_browser.png)
 
 Congratulations, you now have a Drupal workspace running.
 

> Don't forget to stop the workspace. Frederik's Visa card will thank you!

![Screenshot of the open in browser](assets/codespace_stop.png)

### Logging in to Drupal

Navigate in your browser to `/user` in the Drupal site.
Log in to the Drupal instance using the provided credentials:

| Role | Username (U) | Password (P) |
| :--- | :----------- | :----------- |
| Admin | `admin`  | `davos`  |

### Enabling the modules
Enable the following modules.
- Ai
- Ai Automator
- Ai_ckeditor
- Ai_content_suggestions
- Ai_translate
- Ai_image_alt_text
- Ai_agents
- Ai_assistant
- Litellm provider
- Postgres vdb provider

### Configuring AI Providers (The Key)

You will need to configure the AI gateway to allow the modules to communicate with large language models (LLMs).

1. Add the **Lite LLM provider key** (manage keys here: `/admin/config/system/keys`). 
2. Navigate to the configuration section for **providers** `/admin/config/ai/providers`.
3. Configure the LLM provider with the following details:
    * **AI Gateway:** `https://dev-playground.gateway.dropsolid.ai` 
    * The specific **AI API KEY** (`sk-***`) will be provided by the instructor.

### Configuring AI Defaults

Navigate to **`/admin/config/ai/settings`** to configure the core AI settings.

1.  Configure the **default AI chat model**.
![test the chat LLM](assets/test_chat.png)
2.  Configure the **default Translation model**.
![test the chat LLM](assets/translate.png)
3.  **Test it out:** 
	1. Navigate to the chat generation explorer `/admin/config/ai/explorers/chat_generator`
	2. Submit the question: `"Who made you?"` 

![test the chat LLM](assets/test_chat.png)

### AI for Editors

This section focuses on using AI to make the content creation and translation process more efficient.

#### 2. Automatic Image Alt Text

1.  Download a picture (e.g. from pexels.com).
2.  Configure the module at **`/admin/config/ai/ai_image_alt_text`**.
![image generation](assets/img_alt_config.png)
- make sure to configure a Model that has vision capabilities (like Gemini-2-5-flash)
- make sure to read the prompt so you understand what will happen.
- Configure the image style that the AI model wil look at.

3.  Create new content at `/node/add/article` and upload the image to **test the automatic alt text generation**.

![image generation](assets/image_gen.png)

Congratulations, You improved accessibility. Visually impaired visitors will now understand what't in the images you upload.

#### AI Assistant in CKEditor

![ai in ckeditor](assets/ckeditor.png)

1. Configure the CKeditor Basic HTML toolbar at `/admin/config/content/formats`. 
2. Add the AI assistant into the active toolbar
3.  **Crucially:** Enable the assistant functionalities in the AI sub configuration, make sure to check the prompts.
![ai in ckeditor](assets/ckeditor_config.png)

4.  Create new content at `/node/add/article` and **test it out**.

Congratulations, you can now configure a tone of voice in the assistant config (for example always speak in spanish pirate speak). And then when editors use the generation, it will take that into account.

#### Automatic Translations

1.  Configure automatic translations at **`/admin/config/ai/ai-translate`**.
2.  Choose an AI model per language and configure the corresponding prompt.
3.  **Test it out** on the node I have provided `/node/1/translations`.

![ai in ckeditor](assets/ai_translate.png)

#### Smart Content Suggestions

1.  Configure Smart Content suggestions at **`/admin/config/ai/suggestions`**.
![ai in ckeditor](assets/content_suggestions.png)
2.  Enable multiple of the available suggestions.
3.  Change the prompt for any suggestion if needed.
4.  **Test it out** on the node I have provided `/node/1/edit`.

![ai in ckeditor](assets/content_sugg.png)

### AI Automators

This section focuses on using AI to automate content creation and data import.

#### Exercise 1: Automatic Derived Social Media Content

**Goal:** Automatically propose social media content based on the article's body, helping marketers skip straight to the end-redaction phase.

1.  Ensure the `AI automator` module is enabled.
2.  Add a **text field** to the **Article content type** (e.g., `field_social_media_draft`).
3. Enable the AI automator Checkbox in the field configuration.
4.  Configure the automator to generate content into this field based on the article body.
	1. Automator Type: **LLM: Text**
	2. Automator Input mode: **Base Mode**
	3. Automator Base field: **Body**
	4. Automator Prompt:
````
INSTRUCTION
----------
From the context below generate a very short summary that is suitable for social media. 
Use a limited amount of emoji;s and put a newline after every sentence. 
Keep it brief and business.
It's for linkedin.

CONTEXT
-------
{{ context }}
````
5.  **See it working** by updating the existing node or saving a new Article node.

![automated social media text](assets/automator_config.png)



#### Exercise 2: Yoast stuff (@DB / 1X)
TODO TODOTODO TODOTODO TODOTODO TODOTODO TODO


### AI Powered Search (RAG)

This section focuses on creating a Retrieval-Augmented Generation (RAG) pipeline for intelligent search. The end result is a GPT style search.

![ai powered search](https://www.drupal.org/files/project-images/ezgif.com-coalesce.gif)

#### RAG Setup

1.  Create a **Vector DB Key**  (manage keys here: `/admin/config/system/keys`).
2.  Set the vector provider (VDB) setting, configuring the **Postgres VDB provider**.

![postgres VDB configuration](assets/vdb_config.png)
- use the username/port/Host/database provided by the course teacher.
- Select the right vector DB Key from the keys list (This is not the LLM key, this is a separate key).

#### Search Indexing

1.  Create a **Search API search server**.

- Use the `Litellm Embeddings` engine
- Use the `2-5-Flash` Chat counting model
- Use the `Postgres Vector Database`
- Use Database name `DB_NAME`
- Use Collection name `frederiks_collection` But replace Frederik with your unique identifier. Like your drupal org account or something.
![search_api config](assets/search_api_server1.png)
![search_api config](assets/search_api_server2.png)

**Tip:** Name your collection uniquely, *unless you like chaos*.

2.  Create a **search index**.

    * Add the relevant content types to the index (double check this as the copy in the search_api screen is misleading).
    * Add the rendered_item (**Full**) to the index. And make sure you select `Main content`.

![search_api config](assets/search_api_index.png)

3. Take some time to create some nodes from wikipedia pages  (copy the wikiedia page content and just paste it in the ckeditor of the body field) or other web content, Asking your site questions is way more fun if you have more content. 

5.  Index the content. It's normal this takes some time.

#### Placing the blocks

Place the required **search blocks** (2 blocks are needed).

![search_api config](assets/search_blocks.png)

The AI search block needs some configuration.
Most of the default configuration is good, but make sure to

- Select the right search_api_index 
- Select a good LLM model
- Review the RAG prompt
- Disable the access check.
- Make sure both blocks shows on the frontpage.

#### Testing the AI search
**Test it out** by performing a search query.
Navigate to your frontpage and ask it questions about your recently created content. 

Congratulations, you have now added AI search to your website.

---

## 🧑‍💻 Advanced: Agents and Assistants

This section covers the creation and testing of AI agents and assistants.

###  **Create an Agent:**
Navigate to **`/admin/config/ai/agents`** and define a new agent. We'll call it `Freddy` but you can choose any name for your agent. 
Make sure the description is a good one as the AI assistent will use it to determine if it is relevant for our course. 
Here's an example Description and prompt:
Description
````
This is an agent that can provide information about the Drupal AI courses.
````
Prompt:
````
-   Role and Context You are an expert technical assistant for the "Drupal & AI - GETTING STARTED Workshop." Your primary function is to help users navigate, configure, and troubleshoot all steps of this workshop based exclusively on the provided documentation. Be helpful, precise, and use the exact configuration paths and credentials from the guide.
    
-   Workshop Overview and Goal Title: Drupal & AI - GETTING STARTED Workshop (AI for editors / AI search / Automated content input). Goal: To provide hands-on experience integrating and leveraging AI within Drupal, focusing on: enhancing the editorial experience, implementing AI-powered search (RAG), and setting up automated content flows.
    
-   Core Setup and Prerequisites Prerequisite: GitHub user account. Path/Command: N/A. Workspace Setup: Navigate to [https://github.com/codespaces/new](https://github.com/codespaces/new), select repository woutersf/drupal-ai-workshop, then right-click port 80 to "Open in Browser." Path/Command: N/A. Stop Workspace: Crucial to stop the codespace after use to save costs. Path/Command: N/A. Drupal Login: URL: /user, Admin U: admin, Admin P: davos. Path/Command: N/A. Required Modules: Ai, Ai Automator, Ai_ckeditor, Ai_content_suggestions, Ai_translate, Ai_image_alt_text, Ai_agents, Ai_assistant, Litellm provider, Postgres vdb provider. Path/Command: N/A. LLM Provider Config: Key Management: /admin/config/system/keys. Provider Config: /admin/config/ai/providers. Path/Command: [https://dev-playground.gateway.dropsolid.ai](https://www.google.com/url?sa=E&source=gmail&q=https://dev-playground.gateway.dropsolid.ai). AI Defaults: Configure default Chat and Translation models. Path/Command: /admin/config/ai/settings. Testing Chat: Submit "Who made you?" Path/Command: /admin/config/ai/explorers/chat_generator.
    
-   AI for Editors Configuration (Practical Use) Auto Image Alt Text: Requires a vision model (e.g., Gemini-2-5-flash). Configure prompt and image style. Test on /node/add/article. Path: /admin/config/ai/ai_image_alt_text. CKEditor Assistant: Add the assistant button to the Basic HTML toolbar. Crucially, enable functionalities and configure the pre-prompt for a custom tone of voice (e.g., "Spanish pirate speak"). Path: /admin/config/content/formats. Automatic Translations: Configure AI model and prompt per language. Test with the provided node. Path: /admin/config/ai/ai-translate. Smart Suggestions: Enable multiple suggestions and change prompts as needed. Test with the provided node. Path: /admin/config/ai/suggestions.
    
-   AI Automators (Workflow Automation) Goal: Automate content creation (e.g., derived social media content). Steps (Social Media Example): 1. Add a text field (e.g., field_social_media_draft) to the Article content type. 2. Enable the AI automator Checkbox in the field config. 3. Automator Type: LLM: Text. Input: Base Mode, Base Field: Body. 4. Key Prompt: Use the provided multi-line prompt to generate a brief, business-like, LinkedIn summary with limited emojis. Note on Exercise 2: The "Yoast stuff" exercise is currently a TODO placeholder in the documentation.
    
-   AI Powered Search (RAG Implementation) Goal: Create a GPT-style Retrieval-Augmented Generation (RAG) search pipeline. Vector Database (VDB) Setup: 1. Create a separate Vector DB Key. 2. Configure the Postgres VDB provider with provided host/port/database details. Search API Server: 1. Engine: Litellm Embeddings. 2. Chat Model: 2-5-Flash. 3. VDB: Postgres Vector Database. 4. Collection Name: MUST be unique (e.g., frederiks_collection). Search Index: 1. Add relevant content types. 2. Index rendered_item (Full) and select Main content. 3. Crucially: Create several nodes (e.g., from Wikipedia pages) to enrich the search results before indexing. Display: Place two search blocks on the front page. Ensure the AI search block is configured with the correct index, LLM model, and the RAG prompt is reviewed. Disable the access check.
    
-   Advanced Topics Agents and Assistants: Create an agent (e.g., name: Freddy, good description required) at /admin/config/ai/agents. Integration: Use the Deepchat Block to connect to the custom agent/assistant.
    
-   Further Resources Homework/Reading: Links provided for Automate Fact-Checking ([https://workflows-of-ai.com/workflow/automize-fact-checking](https://www.google.com/url?sa=E&source=gmail&q=https://workflows-of-ai.com/workflow/automize-fact-checking)), Automatic Podcast Generation ([https://workflows-of-ai.com/workflow/automatic-podcast](https://www.google.com/url?sa=E&source=gmail&q=https://workflows-of-ai.com/workflow/automatic-podcast)), and Migrate Without Code ([https://workflows-of-ai.com/workflow/migrate-without-code](https://www.google.com/url?sa=E&source=gmail&q=https://workflows-of-ai.com/workflow/migrate-without-code)). Instructor Contact: Frederik Wouters at frederik.wouters@dropsolid.com or Dr. Christoph Breidert from 1X internet.
````

2.  No need to select tools for now. Click on Explore to **Test your agent**.
Ask your agent a question about this workshop eg. `Who created this course?`. 
###  **Create an Assistant:**
1.  **Create an AI Assistant**.
Navigate to `/admin/config/ai/ai-assistant` and create an assistant there. I'm calling it `Demo assistant`, but you can choose whatever name there.
2. **Configure your assistant** 

- Description eg `The ai assistant for in the demo`
- Instructions
````
Persona
----------
You are Demo, the ai drupal assistant. 

Tone of voice
-------------
you always answer in a professional manner, not too verbose, you dont repeat the questions, but brief and functional responses. 

Language
----------
You can answer in the language of the user. If you're not sure , fallback to english.

Scope
------
You only have access to the content type agent and the taxonomy agent, so you can only do those actions. 
````

Make sure to enable one or two agents in your assistan. 
I'm enabling my recently created Agent (Freddy) and the Content Type Agent. 
Also make sure to select a good Model from the LiteLLM provider.

2.  **Test your assistant**.
We can not test the assistant from the assistants overview. 
We will add a chatbot block to the frontpage and test it there.

###  **Create a Chat block:**

3.  **Integrate the Chatbot:**
    * Add a **Deepchat Block** to the content section.

![adding deepchat to the blocks](assets/deepchat.png)
![Deepchat assistant selection](assets/deepchat2.png)

3.  **Test the Chatbot:**
test the chatbot on the frontage by asking it a question from the course agent. You'll see it dispatching the question to the right agent. Then ask it a question about another agent (eg. content types) and you'll see it consulting the content-type agent for the response. 

4.  **Add the RAG to the chatbot**

Add the RAG agent to the chatbot so it is able to search in the content we created. 


### 📧 Questions?

If you have any questions during the workshop, please feel free to reach out to the instructors.