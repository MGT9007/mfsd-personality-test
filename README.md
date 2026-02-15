# MFSD Personality Test Plugin

Version: 1.0.0

## Description

A standalone WordPress personality test plugin featuring MBTI (Myers-Briggs Type Indicator) and DISC personality assessments with AI-powered summaries and insights.

## Features

- **Week-based Configuration**: Questions can be configured to appear in specific weeks (1-6)
- **Multiple Test Types**: Supports both MBTI and DISC personality assessments
- **Progress Tracking**: Visual progress bar shows completion percentage
- **AI Integration**: 
  - AI-generated intro messages
  - Question-specific guidance
  - Context-aware chatbot support
  - Comprehensive personality summaries
- **Visual Results**: 
  - MBTI type display with descriptions
  - DISC polar plot visualization
  - Score breakdowns
- **Smart Navigation**:
  - Returns to intro if not started
  - Resumes from last unanswered question if in progress
  - Shows results if completed

## Installation

1. Upload the entire plugin folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress admin
3. The plugin will automatically create database tables and insert sample questions

## Usage

### Shortcode

Add the personality test to any page using:

```
[mfsd_personality_test]
```

### Page Title Convention

The plugin reads the week number from the page title. Use formats like:
- "Week 1 Personality Test"
- "Week 2 - Personality"
- "Personality Test Week 3"

The plugin will extract the week number (1-6) automatically.

## Admin Interface

Access the admin interface from **WordPress Admin → Personality Test**

### Adding MBTI Questions:
1. Select question type: MBTI
2. Choose the axis (1-4)
3. Choose the letter that a "Green" answer supports
4. Enter question text
5. Select weeks to enable

### Adding DISC Questions:
1. Select question type: DISC
2. Enter question text
3. Set contribution values (typically 0 or 1) for each dimension
4. Select weeks to enable

## Files Included

- `mfsd-personality-test.php` - Main plugin file
- `admin-page.php` - Admin interface HTML
- `assets/mfsd-personality-test.js` - Frontend JavaScript
- `assets/mfsd-personality-test.css` - Styling
- `README.md` - This file

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- MWAI plugin (for AI features)

## Author

MisterT9007
