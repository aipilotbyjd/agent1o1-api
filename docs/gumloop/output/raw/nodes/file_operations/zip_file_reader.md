> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Zip File Reader

The `Zip File Reader` node extracts and reads file contents from a zip archive, supporting various file formats for efficient batch processing and analysis.

## Node Inputs

### Required Input

* **Zip File Name**: Upload the zip file or specify an existing zip file from storage.

## Node Outputs

After successfully reading the zip file, the node provides two outputs:

1. **File Names**: A list of strings with the names of each file inside the zip.
2. **File Contents**: A list of strings containing the extracted text or data from each file within the zip.

## Node Functionality

The `Zip File Reader` node performs the following tasks:

1. Fetches the specified zip file.
2. Extracts the contents of supported file types, including PDF, JSON, CSV, and TXT.
3. Returns a list of file names and their corresponding content.
4. Logs successful operation completion.

## Supported File Types

* **PDF**: Extracts text content from PDF documents.
* **JSON**: Reads and parses JSON files.
* **CSV**: Extracts comma-separated values as text.
* **TXT**: Reads plain text files.

## Common Use Cases

1. **Bulk Document Processing**: Easily extract data from multiple documents stored within a zip file for downstream analysis.
2. **Data Ingestion for Analysis**: Use in workflows that require the extraction of structured data from CSV and JSON files for data processing.
3. **Text Extraction for Content Review**: Useful for pulling text from reports, logs, or archived notes within a zip file for content analysis.

## Usage Tips

* **Batch Mode**: This node supports loop mode, allowing for the analysis of multiple zip files.
* **Consistent Output Order**: The `file_names` and `file_contents` lists maintain the same order, meaning the content of each file aligns with its name in the output.

The `Zip File Reader` node streamlines the extraction of data from compressed files, making it ideal for workflows requiring quick access to multiple documents stored in a zip format.
