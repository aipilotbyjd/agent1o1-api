> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Store in S3

This document outlines the functionality and characteristics of the Store in S3 node, which enables automated file uploading to Amazon S3 buckets.

## Node Inputs

### Required Fields

* **Stored File Name**: Name to give the file in S3 (including extension)
* **S3 Bucket**: Target bucket name
* **File URL**: Source URL of file to upload

### Optional Field

* **Overwrite**: Toggle to allow overwriting existing files

## Node Output

* **S3 File URI**: Access URI for the uploaded file

## Node Functionality

The Store in S3 node uploads files to Amazon S3 from URLs.

**Key features include**:

* Direct file uploading
* Overwrite option
* Custom file naming
* URI generation
* Secure authentication with Gumloop

## When To Use

The Store in S3 node is valuable for automated file storage in AWS. Common use cases include:

* **File Backup**: Store important files in the cloud
* **Media Storage**: Upload images and documents
* **Data Archiving**: Save processed data files
* **Content Distribution**: Store files for CDN access

**Some specific examples**:

* Backing up daily reports as PDFs
* Storing processed images for web use
* Archiving generated data files
* Uploading assets for web applications

## Important Considerations:

1. Requires Authentication with AWS - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Bucket must exist and be accessible

In summary, the Store in S3 node provides reliable file uploading to Amazon S3, ideal for automated cloud storage workflows.
