
Spin up the gitpod.


`docker exec -it my_drupal11_project_php /bin/bash`
`vi web/sites/default/settings.php`
Add to the bottom: 
````
$settings['trusted_host_patterns'] = array(                                                                                                                
    '\.gitpod\.io', '\.localhost$', '\.local$', '\.loc$'                                                                                                   
);
````
next steps
composer require drupal/ai drupal/admin_toolbar drupal/ai_api drupal/ai_block drupal/ai_image drupal/ai_provider_mistral   drupal/ai_vdb_provider_pinecone
-        "drupal/admin_toolbar": "^3.4", 
-        "drupal/ai": "^1.0",
-        "drupal/ai_api": "^1.0@dev",
-        "drupal/ai_block": "^1.0",
-        "drupal/ai_image": "^1.0@beta",
-        "drupal/ai_provider_mistral": "^1.0@beta",
-        "drupal/ai_search_block": "^1.0@dev",
-        "drupal/ai_vdb_provider_pinecone": "^1.0@beta"

Stability: 
composer require drupal/ai_api drupal/ai_search_block

- Configure Mistral key
- Configure AI defaults
- Configure content suggestions
- Configure Ai ckeditor
- Configure Ai search
- Configure ai_search_block
- 
