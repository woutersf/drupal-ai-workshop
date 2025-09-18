# Postgres Vector Database Provider

Provides a drupal/ai VdbProvider plugin that interfaces with a postgreSQL
database instance, using the vector extension, provided by pgvector.

## pgvector

pgvector implements the postgreSQL extension that provides vector similarity
search.

See https://github.com/pgvector/pgvector for more information.

## Requirements
* Drupal AI module
* pgsql PHP extension enabled
* A postgreSQL database instance with the pgvector `vector` extension enabled

## Configuration

Configure the module at `/admin/config/ai/vdb_providers/postgres`.

## Using with docker compose

### Web container

Ensure the pgsql PHP extension is enabled on your web container.

See `./docs/docker-compose-examples/Dockerfile` for an example of how this can
be done.

This is specific to your web container image and the package manager it uses.

### pgvector

An example docker compose setup of a pgvector instance is provided.

See `./docs/docker-compose-examples/postgres-docker-compose`, where
`./docs/docker-compose-examples/docker/vector_db/init` maps to your local
`./docker/vector_db/init/` relative to your `docker-composer.yml` file.
