# Pinecone Vector Database Provider

## Overview

This Drupal module provides integration with Pinecone, a managed vector database
service. It includes features for inserting, deleting, and managing vector data.

This allows you to use the AI Search features from the AI module including its:
- Ability to find highly relevant results from keywords or full sentence 
  queries
- Integration with Views via Search API, including boosting of Search API
  Database and Search API SOLR setups
- Ability to use as a source for Retrieval Augmented Generation (RAG)

## Requirements

- Pinecone serverless account (starter is fine) and API key.

## Installation

1. (In Drupal) Enable this module (which requires the AI and Search API modules)
2. (In Drupal) Ensure you have an Embedding type configured in the AI Core
   module configuration at Admin > Configuration > AI.
3. (In Drupal) Configure the API connection to Pine via Admin > Configuration >
   Vector Database Providers > Pinecone.
4. (In Pinecone) Create an Index in the Pinecone UI via pinecone.io.
5. (In Drupal) Create a new Search API Server, select Pinecone as the backend,
   and select that Index.
6. (In Drupal) Set up AI Search as desired (see AI Search documentation).

## Contributing to the Pinecone PHP library dependency.

See https://github.com/probots-io/pinecone-php

## Compatibility with Pinecone options

This module is intended to support the 'Serverless' architecture only. The VDB
provider methods around Collections therefore map to Pinecone's namespace
architecture.

If you intend to use Pinecone's Pod infrastructure, that is not yet supported;
however, can be contributed back as a new VDB Provider within this module.
