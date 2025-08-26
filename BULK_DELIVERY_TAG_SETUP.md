# Bulk Delivery Tag Processor Setup

## Overview
The Bulk Delivery Tag Processor allows you to upload a multi-page PDF of scanned delivery tags, automatically split them into individual PDFs, extract order numbers using OCR, and attach them to the corresponding orders.

## System Requirements

### 1. Docker Container Dependencies
The following need to be installed in your Docker container:

```bash
# Install Tesseract OCR
apt-get update
apt-get install -y tesseract-ocr tesseract-ocr-eng

# Install ImageMagick (for PDF to image conversion)
apt-get install -y imagemagick

# Install Ghostscript (for PDF processing)
apt-get install -y ghostscript
```

### 2. PHP Extensions
Ensure these PHP extensions are available:
- `gd` or `imagick`
- `fileinfo`

### 3. ImageMagick Policy Configuration
You may need to update ImageMagick's policy to allow PDF processing:

```bash
# Edit the policy file
nano /etc/ImageMagick-6/policy.xml

# Comment out or modify this line:
<!-- <policy domain="coder" rights="none" pattern="PDF" /> -->

# Change to:
<policy domain="coder" rights="read|write" pattern="PDF" />
```

## Features

### 🔧 **Automatic Processing**
- **PDF Splitting**: Splits multi-page PDFs into individual pages
- **OCR Recognition**: Extracts order numbers using Tesseract OCR
- **Smart Matching**: Matches order numbers to existing orders
- **Batch Processing**: Handles multiple pages in one upload

### 📊 **Results Dashboard**
- **Processing Statistics**: Total pages, processed, matched, unmatched
- **Success Rate**: Visual progress bar
- **Error Reporting**: Detailed error messages for failed pages
- **Unmatched Tags**: List of tags that couldn't be matched to orders

### 🎯 **OCR Optimization**
- **Pattern Recognition**: Looks for "No. XXXXX" format
- **Multiple Patterns**: Fallback patterns for OCR variations
- **High Resolution**: 300 DPI for better text recognition
- **Character Filtering**: Optimized for digits and relevant characters

## Usage

### 1. Access the Tool
Navigate to: **Orders → Bulk Tag Processor**

### 2. Upload PDF
- Upload a multi-page PDF containing scanned delivery tags
- Maximum file size: 50MB
- Supported format: PDF only

### 3. Processing Options
- **Dry Run Mode**: Test processing without attaching to orders
- **High Quality OCR**: Use higher resolution for better accuracy

### 4. Review Results
- Check processing statistics
- Review unmatched tags
- Handle any errors manually

## Order Number Formats Supported

The OCR system recognizes these patterns:
- `No. 48588` (primary format)
- `N0. 48588` (OCR misread)
- `No 48588` (space instead of period)
- `48588` (just the number)

## File Naming Convention

Individual delivery tag PDFs are saved as:
```
delivery_tag_{ORDER_NUMBER}_{TIMESTAMP}.pdf
```

Example: `delivery_tag_48588_1703123456.pdf`

## Troubleshooting

### Common Issues

1. **OCR Not Working**
   - Ensure Tesseract is installed in Docker container
   - Check ImageMagick permissions for PDF processing

2. **PDF Splitting Fails**
   - Verify FPDI package is installed
   - Check PDF is not password protected

3. **No Order Numbers Found**
   - Ensure delivery tags have clear, readable text
   - Try High Quality OCR option
   - Check if order numbers are in expected format

### Debugging
- Check Laravel logs for OCR errors
- Use Dry Run mode to test without making changes
- Review unmatched tags section for pattern issues

## Performance Notes

- Processing time depends on PDF size and page count
- OCR is CPU intensive - expect ~2-5 seconds per page
- High Quality OCR mode is slower but more accurate
- Large PDFs (>20 pages) may take several minutes

## Security

- All uploaded files are temporarily stored and cleaned up
- Individual PDFs are stored in R2 with private visibility
- No sensitive data is logged during OCR processing

