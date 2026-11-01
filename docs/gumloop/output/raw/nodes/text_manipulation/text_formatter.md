> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Text Formatter

## Node Inputs

* **Value**: The text that you want to format.
* **Formatter**: The formatting you want to apply to the given text. Options include:
  * To Lowercase
  * To Uppercase
  * To Propercase
  * Trim Spaces
  * Truncate

## Node Output

* **Formatted Value**: The text after the specified formatting has been applied to it.

## Node Functionality

The Text Formatter node formats a given text based on the specified formatting condition. It provides a variety of common text manipulations, allowing you to convert text to all lowercase or uppercase, capitalize the first letter of each word, remove any extra spaces at the beginning or end, or shorten the text to a defined number of tokens.

### When To Use

Use the Text Formatter node when you need to standardize textual data. Here are some scenarios where it can be particularly useful:

* **To Lowercase**: When you want all the text to be in lowercase for uniformity, like converting email addresses or usernames.
* **To Uppercase**: When you need the text to be in uppercase, which can be helpful for making titles or acronyms stand out.
* **To Propercase**: When you want to make sure that each word starts with a capital letter, which is often used for names or titles.
* **Trim Spaces**: When you need to clean up the text by removing leading and trailing spaces that may have been mistakenly added.
* **Truncate**: When there's a need to shorten textual data to a specific length, such as summarizing content or adhering to character limits in databases or other systems.

Overall, the Text Formatter node helps in tidying up text data, making sure it fits the format that your workflow or database requires.
