> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Filter

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1059000256" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="Filtering" />
</div>

This document explains the Filter node, which lets you selectively pass data through your workflow based on conditions.

## Node Inputs

### Required Fields

* **Filter By**: The value to test against your condition
* **Input**: The actual data you want to pass through (if condition is met)

### Optional Fields

* **Output Blank Value**: When enabled, outputs blank values for filtered items instead of skipping them
* **Configure Inputs**: Lets you make 'conditional value' field dynamic. Helpful for loop mode

## Node Output

* **Filtered Output**: Data that passes your filter condition

## Node Functionality

The Filter node works like an "if statement" in your workflow. It checks if `Filter By` meets your condition, and if so, passes through the corresponding `Input` value(s).

## Filter Types Explained

### Number Filters

* **Is greater than**: Passes values above your number
* **Is less than**: Passes values below your number
* **Equals**: Passes exact number matches

### Text Filters

* **Contains**: Checks if text includes your value
* **Is in**: Checks if the exact text is included in your input
* **Starts with**: Matches beginning of text
* **Ends with**: Matches end of text

**Note**: Text filters are case sensitive.
**Tip**: You can use the 'Text Formatter' node before the filter node to avoid case sensitive issues.

### State Filters

* **Is empty**: Passes empty/null values
* **Is not empty**: Passes any non-empty value
* **Is true**: Passes boolean true values
* **Is false**: Passes boolean false values

## Loop Mode Tips

1. **Dynamic Conditions**:
   * Use Configure Inputs to make conditions dynamic
   * Example: Filter prices above a certain threshold. [Example Workflow](https://www.gumloop.com/pipeline?workbook_id=rryEtJNtdmKmEs4NHL661o)

2. **Multiple Filters**:
   * Chain multiple filter nodes for complex conditions
   * Example: Price > 100 AND Contains "premium". [Example Workflow](https://www.gumloop.com/pipeline?workbook_id=w8hNhjeaBPvafkm7dbzS9B)

## Important Considerations

1. Loop mode processes each item independently
2. Consider Output Blank Value impact on downstream nodes
3. For loop mode if you've a single conditional value against a list of inputs, use the 'Duplicate' node for the conditional value to avoid list size mismatch errors

## Additional Information

[Video Tutorial](https://www.youtube.com/watch?v=2yDaQvYDeBM)

In summary, the Filter node is your workflow's decision maker, letting you precisely control what data continues through your automation based on flexible conditions.
