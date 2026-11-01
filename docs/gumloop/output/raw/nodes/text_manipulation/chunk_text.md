> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Chunk Text

## Node Inputs

* **Chunk Size**: This is an integer value that specifies the number of characters each piece of the text should contain after chunking. For example, if you set this to 100, the text will be split into chunks where each chunk has 100 characters. If the text doesn't divide evenly, the last chunk will contain whatever characters remain.
* **Text**: This is the body of text that you want to split up into smaller pieces or chunks.

## Node Output

* **Text Chunks**: This is the output which consists of a list of text pieces. Each piece in this list is text with the number of characters specified by the `chunk size` input. If the text is 1000 characters long and the `chunk size` is set to 100, you'll receive a list of 10 text chunks.

## Node Functionality

The Chunk Text node has a simple yet powerful function. It takes a long string of text and divides it into smaller, more manageable pieces based on the number of characters you specify. Think of it like taking a long roll of paper and cutting it into equal lengths. If at the end the last piece is shorter, that is okay, as the goal is to make no piece longer than the specified size.

## When To Use

You might want to use the Chunk Text node when handling large texts that need to be processed in smaller sections. For example:

* When working with limitations on text size, such as API requests that only accept a certain number of characters at a time.
* If you're displaying text to users in a limited space and need to ensure it fits properly, chunking can help divide it into sizeable sections.
* For text analysis, where analyzing smaller sections can be more manageable or where you might need to compare chunks of text against each other.
* When you need to provide a summary or preview of a text where only a specific character count is allowed.
