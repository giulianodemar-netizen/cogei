# Directory for Document Request Attachments

This directory stores PDF attachments uploaded when requesting documents from suppliers.

## Security
- Files are renamed with `userID_timestamp_originalname.pdf` format
- Only PDF files are accepted (MIME type validation)
- Maximum file size: 10MB per file
- PHP execution is disabled in this directory (see .htaccess)

## Maintenance
- Review and clean up old attachments periodically
- Attachments are linked in emails sent to suppliers
