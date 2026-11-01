> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Similarity Search

This node allows you to search through a large text by breaking it down and finding the parts that are most relevant to your search terms, similar to how you might search for a specific topic in a book by looking at the index.

## Node Inputs

* **Query**: A search term or phrase you're looking for in the body of text.
* **Num Results**: The number of relevant text sections you want to retrieve that match your query.
* **Chunk Size** (Optional): The size of each text section, which by default is 1000 tokens (roughly corresponds to words or punctuation marks).
* **Text**: The large body of text you want to search within.

## Node Output

* **Relevant Chunks**: This is a list of text "chunks" or sections that have been determined to be the most relevant to your search query.

## Node Functionality

The node processes your search query to understand what you're looking for. It then divides the large text into smaller, more manageable sections called chunks. Each chunk is analyzed for its relevance to your query. The node sorts these chunks based on their similarity to your query and returns the number of top matches you specified. It's much like having an incredibly fast reader skim through every page of a book to find exactly the parts that interest you the most.

### When To Use

Use this node whenever you need to sift through substantial amounts of text to find specific information quickly. This can be extremely useful in activities such as researching a certain topic within large documents, filtering through extensive reports to find mention of specific details, or extracting key sections from long articles.
