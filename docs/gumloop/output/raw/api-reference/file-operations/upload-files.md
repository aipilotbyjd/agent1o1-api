> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Upload multiple files



## OpenAPI

````yaml post /upload_files
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /upload_files:
    post:
      tags:
        - File Handling
      summary: Upload multiple files
      operationId: uploadFiles
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                files:
                  type: array
                  items:
                    type: object
                    properties:
                      file_name:
                        type: string
                        description: The name of the file to be uploaded.
                      file_content:
                        type: string
                        format: byte
                        description: Base64 encoded content of the file.
                user_id:
                  type: string
                  description: >-
                    The user ID associated with the files. Required if
                    project_id is not provided.
                project_id:
                  type: string
                  description: >-
                    The project ID associated with the files. Required if
                    user_id is not provided.
      responses:
        '200':
          description: All files uploaded successfully
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                  uploaded_files:
                    type: array
                    items:
                      type: string
                    description: Array of uploaded file names.
        '207':
          description: Partial success (some files failed to upload)
          content:
            application/json:
              schema:
                type: object
                properties:
                  partial_success:
                    type: boolean
                  uploaded_files:
                    type: array
                    items:
                      type: string
                    description: Array of successfully uploaded file names.
                  failed_files:
                    type: array
                    items:
                      type: string
                    description: Array of file names that failed to upload.
        '400':
          description: Bad request (missing files or required parameters)
        '401':
          description: Unauthorized (missing or invalid API key)
        '403':
          description: Forbidden (API key does not match)
        '500':
          description: Internal server error (all file uploads failed)
      security:
        - bearerAuth: []
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      description: >-
        A personal API key or an [OAuth 2.0](/api-reference/oauth) access token.
        Personal API keys also require the `x-auth-key` header with your user
        ID.

````