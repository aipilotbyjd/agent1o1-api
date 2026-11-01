> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# AI Fill PDF

This document explains the AI Fill PDF node, which automatically fills out PDF forms using AI understanding of context.

## Node Inputs

### Required Fields

* **Context**: Information to use for filling
* **PDF File**: Form to be filled (must have fillable fields)

### Optional Fields

* **Specify Pages**: Fill specific pages only
* **Image Model**: Choose AI model
* **Temperature**: Controls filling accuracy (0-1)
* **Cache Response**: Save results for reuse

### Show As Input Options

You can expose these fields as inputs:

* Temperature

## Node Output

* **Filled PDF File**: Completed form

## Node Functionality

The AI Fill PDF node:

* Reads form fields
* Understands field context
* Maps data appropriately
* Fills form fields
* Preserves PDF structure

## Available AI Models

* GPT-5.4 Vision
* GPT-5.4 Mini Vision
* Claude 4.6 Sonnet
* Claude 4.5 Haiku
* Gemini 3.1 Pro
* Gemini 3.7 Flash

## Common Use Cases

1. **HR Forms**:

```text theme={"dark"}
Context: Employee data
PDF: Employment forms
Result: Completed paperwork
```

2. **Applications**:

```text theme={"dark"}
Context: Applicant details
PDF: Application form
Result: Filled application
```

3. **Legal Documents**:

```text theme={"dark"}
Context: Case information
PDF: Legal templates
Result: Prepared documents
```

## Important Considerations

1. Advanced models (GPT-5.4, Claude 4.6 Sonnet) cost 20 credits, and standard models cost 2 credits per run
2. PDF must have fillable fields
3. Context should be relevant

## Additional Information

[Video Tutorial](https://www.youtube.com/watch?v=UBS0hxuqsHw)

In summary, the AI Fill PDF node automates form filling by intelligently mapping provided information to PDF form fields.
