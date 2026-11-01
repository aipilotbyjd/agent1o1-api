> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Download artifact

> Returns a signed download URL for an artifact, plus its filename, media type, and size. Follow `download_url` to fetch the file bytes.



## OpenAPI

````yaml get /artifacts/{artifact_id}/download
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /artifacts/{artifact_id}/download:
    get:
      tags:
        - Artifacts
      summary: Download artifact
      description: >-
        Returns a signed download URL for an artifact, plus its filename, media
        type, and size. Follow `download_url` to fetch the file bytes.
      operationId: downloadArtifact
      parameters:
        - in: path
          name: artifact_id
          required: true
          schema:
            type: string
          description: ID of the artifact to download.
        - in: query
          name: version_id
          required: false
          schema:
            type: string
          description: >-
            Specific version of the artifact to download. Defaults to the latest
            version when omitted.
      responses:
        '200':
          description: Signed download URL and file metadata.
          content:
            application/json:
              schema:
                type: object
                properties:
                  download_url:
                    type: string
                    description: Signed URL the caller can `GET` to fetch the file bytes.
                    example: >-
                      https://storage.googleapis.com/gumloop-artifacts/art_aBcDeF123?X-Goog-Signature=...
                  filename:
                    type: string
                    nullable: true
                    example: q4_sales_report.pdf
                  media_type:
                    type: string
                    nullable: true
                    example: application/pdf
                  size:
                    type: integer
                    nullable: true
                    description: File size in bytes.
                    example: 12345
                required:
                  - download_url
              examples:
                pdf:
                  summary: PDF artifact
                  value:
                    download_url: >-
                      https://storage.googleapis.com/gumloop-artifacts/art_aBcDeF123?X-Goog-Signature=...
                    filename: q4_sales_report.pdf
                    media_type: application/pdf
                    size: 12345
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: Forbidden — the caller does not have read access on the artifact.
        '404':
          description: >-
            Artifact not found, version not found, or the underlying file is
            unavailable.
        '502':
          description: Failed to generate a download URL for the underlying file.
      security:
        - bearerAuth: []
      x-codeSamples:
        - lang: bash
          label: cURL
          source: >
            curl 'https://api.gumloop.com/api/v1/artifacts/ARTIFACT_ID/download'
            \
              -H 'Authorization: Bearer YOUR_ACCESS_TOKEN'
        - lang: python
          label: Python
          source: |
            from gumloop import Gumloop

            client = Gumloop(access_token="YOUR_ACCESS_TOKEN")

            response = client.artifacts.download(artifact_id="ARTIFACT_ID")
            print(response.download_url, response.filename, response.size)
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