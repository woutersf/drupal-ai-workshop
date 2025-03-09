# ai_search_block

## Setting things up

Add the search block and the response area block into the page.
only add one response area into the page. The search block will push the response into the target block.
Configure all fields in the search block.
Make sure you have a
- vector database configured
- ai provider module enabled
- search index configured.

## Usage

1. Type a search term into the search form
2. See the response streaming back into the response area.


## Extending the module.

### Theming

You can theme the blocks they are using a template.
Check `ai_search_block_wrapper` and `ai_search_block_response`.


### Alter the rendered html from the nodes before we turn it into markdown.

This example snippet removes comments and <selects with all their <options> from the html..
````
function ai_search_block_ai_search_block_entity_html_alter(&$rendered_entity, $entity){
  $lines = explode(PHP_EOL, $rendered_entity);
  $newlines = [];
  foreach($lines as $line) {
    $line = preg_replace('/<!--(.|\s)*?-->/', '', $line);
    if (str_contains($line, 'select')) {
      $line = preg_replace('/(<select[\s\w*\-*\=\"]*>.*<\/select>)/gim', '', $line);
    }
    $line = preg_replace('/\s\s+/', ' ', $line);
    $newlines[] = $line;
  }
  $rendered_entity = implode($newlines);
}
````

### Alter the markdown of the entity before it goes into the query

This example snippet cleans up multiple empty lines into one empty line.
````
function ai_search_block_ai_search_block_entity_markdown_alter(&$markdown, $entity){
  $lines = explode(PHP_EOL, $markdown);
  $newlines = [];
  $prev = NULL;
  foreach ($lines as $line) {
    $newline = trim($line, '\t');
    if  ($prev == $newline && $newline == '') {
      continue; // Implicit cleanup of duplicate empty lines.
    }
    $newlines[] = $newline;
    $prev = $newline;
  }
  $markdown = implode(PHP_EOL, $newlines);
}
````

### Alter the prompt that will be used in the end:

This example snippet replaces a self invented "Token" that is in the block config
to replace it with the current time.
````
function ai_search_block_ai_search_block_prompt_alter(string &$prompt) {
  $variable = time();
  $prompt = str_replace('[my_custom_token]', $variable, $prompt);
}
````


