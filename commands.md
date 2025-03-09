
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

- Configure Mistral key
- Configure AI defaults
- Configure content suggestions
- Configure Ai ckeditor
- Configure Ai search
- Configure ai_search_block
- 
