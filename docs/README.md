# Keboola OneDrive Writer

Writes files to user's OneDrive/SharePoint. Existing files are overwritten.

Input tables that are not converted to files are ignored by the writer. (Only `/data/in/files` directory
is processed.)

## Usage

No configuration parameters are required. Files are uploaded to the root of user's personal OneDrive.

### Upload to a specified directory

If *OneDrive directory path* is configured the writer tries to upload all files into the specified
directory under the user's OneDrive account. This can be a single directory of multiple nested
directories, e.g. `Documents` or `Documents\Dogs\2019`.

### Upload tables as XLS(X) 

Convert input tables to XLS(X) files using Processors section:

```
{
  "before": [
    {
      "definition": {
        "component": "kds-team.processor-csv2xls"
      },
      "parameters": {
        "export_mode": "separate_files"
      }
    },
    {
      "definition": {
        "component": "keboola.processor-move-files"
      },
      "parameters": {
        "direction": "files",
        "addCsvSuffix": false
      }
    }
  ],
  "after": []
}
```
