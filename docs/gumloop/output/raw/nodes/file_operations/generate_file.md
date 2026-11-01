> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Generate File

The `Generate File` node converts text input into a file in various formats, automating document creation and allowing for easy storage, sharing, or further processing.

## Node Inputs

* **File Name** (text): Specify a name for the file without including the extension. For example, if creating a receipt, you might use "receipt\_2023". The node will add the correct file extension based on the selected file type.

* **File Type** (enum): Choose the format for the file. Available options are:
  * `.pdf`: Ideal for documents that should retain consistent formatting across devices.
  * `.docx`: Commonly used for formatted text documents, such as reports or letters.
  * `.txt`: Plain text format, used for unformatted text files.
  * `.csv`: Used for structured data in spreadsheet format, suitable for tabular data.
  * `.html`: HTML document format, useful for web-based text or simple web pages.

* **File Contents** (text): The text content or data to be saved within the file. For example, the body of a report, a table of data, or any content that needs to be included in the generated file.

## Optional Parameters

* **Write as Markdown**: For `.pdf` files only. If enabled, it converts Markdown-formatted text into a styled PDF, supporting headers, lists, bold text, etc.

* **Output Link?** (boolean): When enabled, generates a public URL to access the file. This is useful for sharing or downloading the file directly. Keep in mind that public links allow anyone with the URL to access the file.

## Node Outputs

* **Generated File**: Returns the complete generated file.
* **Generated File URL** (text, optional): If "Output Link" is enabled, provides a public URL where the file can be accessed.

## Node Functionality

The `Generate File` node automates the process of converting text input into files in multiple formats. It can be used to create PDFs from Markdown content, generate Word documents for formal text, save notes as TXT files, format data into CSV files, or create HTML documents. This node can handle individual file generation or batch operations, making it suitable for workflows that require frequent or automated document creation.

## When To Use

Use this node whenever you need to programmatically create documents or files. Common use cases include:

* **Automated Document Creation**: Generate receipts, personalized letters, or certificates.
* **Data Export**: Save structured data in CSV format for analysis or reporting.
* **Report Generation**: Create reports in PDF or DOCX formats for sharing with clients or team members.
* **Web Content**: Generate HTML files from text for publishing simple web pages.

## Usage Tips

* **Batch Mode**: Use Loop Mode to generate multiple files at once from a list of inputs, useful for large-scale document creation.
* **Markdown Support**: For PDFs, you can use Markdown formatting to enhance readability and presentation.
* **Public Links**: Be cautious with sensitive content when enabling public URLs, as these links are accessible to anyone with the URL.

The `Generate File` node is a versatile tool for automating document and data file creation, saving time and streamlining workflows for tasks like report generation, data export, and automated document storage.
