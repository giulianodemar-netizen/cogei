# Admin Questionnaire Editing Feature

## Overview

This feature allows WordPress administrators to edit completed questionnaire responses from the back-office admin panel. Admins can modify any answer in a completed questionnaire, and the system will automatically recalculate the score based on the new responses.

## User Story

**As an administrator**, I want to be able to edit completed questionnaire responses so that I can correct mistakes or update evaluations without requiring the evaluator to resubmit the entire questionnaire.

## Feature Location

- **Admin Panel**: WordPress Backend → Questionari
- **Tab**: "Storico Invii" (Submissions)
- **Button**: "✏️ Modifica Risposte" (appears only for completed questionnaires)

## How It Works

### 1. Opening the Edit Form

1. Navigate to the Questionari admin page
2. Click on the "Storico Invii" tab
3. Find a completed questionnaire (status: "completed")
4. Click the "✏️ Modifica Risposte" button below "📊 Visualizza Risultato"
5. A modal dialog opens with the questionnaire form

### 2. Editing Responses

The edit modal displays:
- **Questionnaire Title**: Name of the questionnaire being edited
- **Supplier Information**: Company name being evaluated
- **All Areas**: Grouped questions by thematic area
- **Current Answers**: Pre-selected with blue highlight
- **Option Weights**: Displayed next to each option
- **N.A. Badges**: Yellow badges for "Not Applicable" options
- **Required Fields**: Marked with asterisk (*)

### 3. Saving Changes

1. Select new answers for the questions you want to modify
2. Click "✓ Salva Modifiche" button
3. System validates all required questions are answered
4. System saves the new responses to the database
5. System recalculates the score automatically
6. Success message shows:
   - Green checkmark
   - "Modifiche Salvate con Successo!"
   - New score (0-100 scale)
   - Star rating (0-5 stars)
   - Evaluation level (Eccellente, Molto Buono, Adeguato, Critico, Inadeguato)

### 4. Viewing Updated Results

After saving:
- Click "Chiudi" to close the modal
- Page refreshes automatically
- Click "📊 Visualizza Risultato" to see full updated results

## Score Recalculation

The system uses the same formula as the original questionnaire submission:

```
For each area:
  area_sum = sum of (option_weight for each question in area)
  If option is N.A.: use max_weight of all options for that question
  area_score = area_sum × area_weight

total_score = sum of all area_scores × 100 (converts to 0-100 scale)
```

### Evaluation Thresholds

| Score Range | Stars | Evaluation | Color |
|-------------|-------|------------|-------|
| 85-100 | 4.5-5 ★ | Eccellente | Green (#4caf50) |
| 70-84 | 3.5-4 ★ | Molto Buono | Light Green (#8bc34a) |
| 55-69 | 2.5-3 ★ | Adeguato | Yellow (#ffc107) |
| 40-54 | 2-2.5 ★ | Critico | Orange (#ff9800) |
| 0-39 | 0-1.5 ★ | Inadeguato | Red (#f44336) |

## Security Features

### Admin-Only Access
- Only users with `administrator` capability can edit questionnaires
- Enforced at both UI level (button visibility) and API level (capability check)
- Non-admin users cannot access the edit endpoints

### CSRF Protection
- WordPress nonces used for all AJAX requests
- Nonce action: `boq_edit_questionnaire`
- Invalid or missing nonces result in 403 Forbidden error

### Data Validation
- All input is sanitized and validated
- Required questions must be answered
- Option IDs must be valid and belong to the correct question
- Database transactions ensure data consistency

### Error Logging
- Invalid responses are logged via `error_log()`
- Helps troubleshoot data integrity issues
- Format: `"BOQ Edit: Invalid question_id (X) or option_id (Y) skipped for assignment Z"`

## Technical Details

### Files Modified

#### 1. bo-questionnaires.php
**Changes**:
- Added "✏️ Modifica Risposte" button to Storico Invii table (line ~2102)
- Added `boqEditModal` HTML structure (line ~2596)
- Added JavaScript functions:
  - `boqOpenEditModal(assignmentId)` - Opens edit modal
  - `boqCloseEditModal()` - Closes edit modal
  - `boqSaveEdits(assignmentId)` - Saves edited responses
- Added nonce generation for security

#### 2. ajax_fornitori/get_editable_questionnaire.php (NEW)
**Purpose**: Load questionnaire with current answers for editing

**Accepts**:
- `assignment_id` (POST): ID of the assignment to edit
- `nonce` (POST): CSRF protection token

**Returns**: JSON with HTML form
```json
{
  "success": true,
  "html": "... HTML form with pre-filled answers ..."
}
```

**Security**:
- Checks `current_user_can('administrator')`
- Verifies nonce with `wp_verify_nonce($_POST['nonce'], 'boq_edit_questionnaire')`
- Returns 403 Forbidden if unauthorized

#### 3. ajax_fornitori/save_questionnaire_edits.php (NEW)
**Purpose**: Save edited responses and recalculate score

**Accepts**:
- `assignment_id` (POST): ID of the assignment
- `responses` (POST): JSON object with question_id → option_id mappings
- `nonce` (POST): CSRF protection token

**Returns**: JSON with updated score
```json
{
  "success": true,
  "message": "Modifiche salvate con successo!",
  "score": {
    "value": "85.50",
    "stars": 4.5,
    "evaluation": "Eccellente",
    "eval_class": "excellent",
    "eval_color": "#4caf50"
  }
}
```

**Security**:
- Checks `current_user_can('administrator')`
- Verifies nonce with `wp_verify_nonce($_POST['nonce'], 'boq_edit_questionnaire')`
- Uses database transactions (START TRANSACTION / COMMIT / ROLLBACK)
- Logs invalid responses via `error_log()`
- Returns 403 Forbidden if unauthorized

### Database Tables Affected

#### wp_cogei_responses
**Updates**:
- `selected_option_id`: New selected option
- `computed_score`: Recalculated weight for the option
- `answered_at`: Updated to current timestamp

**Note**: The `assignment_id` and `question_id` remain unchanged

### API Endpoints

Both endpoints require POST method and return JSON:

1. **Get Editable Questionnaire**
   - URL: `/ajax_fornitori/get_editable_questionnaire.php`
   - Method: POST
   - Parameters: `assignment_id`, `nonce`
   - Response: HTML form with current answers

2. **Save Edits**
   - URL: `/ajax_fornitori/save_questionnaire_edits.php`
   - Method: POST
   - Parameters: `assignment_id`, `responses` (JSON), `nonce`
   - Response: Updated score and evaluation

## User Interface

### Edit Button
- **Text**: "✏️ Modifica Risposte"
- **Color**: Orange (#ff9800)
- **Location**: Below "📊 Visualizza Risultato" in Storico Invii table
- **Visibility**: Only for completed questionnaires

### Edit Modal
- **Width**: 95% (max 900px)
- **Height**: 90vh
- **Background**: White with purple gradient header
- **Close**: Click × button or click outside modal
- **Scrollable**: Yes (form content is scrollable)

### Form Fields
- **Radio Buttons**: Single selection per question
- **Visual Feedback**: Selected options highlighted in blue
- **Hover Effect**: Options highlight on hover
- **Validation**: Browser built-in validation for required fields

### Success Message
- **Icon**: Green checkmark (✓)
- **Score Display**: Large font, color-coded by evaluation
- **Stars**: Gold filled stars with empty stars
- **Evaluation Badge**: Color-coded pill with evaluation text

## Browser Support

Tested and compatible with:
- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)

## Performance

- **Load Time**: 1-3 seconds for typical questionnaire (10-20 questions)
- **Save Time**: 2-5 seconds including score recalculation
- **Database**: Single transaction ensures atomicity

## Limitations

1. **Admin Only**: Only administrators can edit questionnaires
2. **Completed Only**: Can only edit questionnaires with status 'completed'
3. **No History**: Previous answers are overwritten (no version history)
4. **No Audit Trail**: Edit actions are logged but not stored in database
5. **Single Assignment**: Edits affect only one assignment at a time

## Future Enhancements

Potential improvements for future versions:

1. **Edit History**: Store edit history with timestamp and admin user
2. **Change Summary**: Show what changed before saving
3. **Bulk Edit**: Edit multiple questionnaires at once
4. **Comment Field**: Add notes explaining why edits were made
5. **Notifications**: Email supplier when their evaluation is updated
6. **Rollback**: Ability to restore previous answers

## Troubleshooting

### Button Not Visible
**Problem**: "Modifica Risposte" button doesn't appear
**Solution**: 
- Verify questionnaire status is "completed"
- Verify you're logged in as administrator
- Check browser console for JavaScript errors

### Modal Won't Open
**Problem**: Clicking button does nothing
**Solution**:
- Check browser console for errors
- Verify AJAX endpoint is accessible
- Check network tab for 403/404 errors

### Can't Save Changes
**Problem**: Save button does nothing or shows error
**Solution**:
- Ensure all required questions are answered
- Check browser console for validation errors
- Verify nonce is being sent in request

### Score Doesn't Update
**Problem**: New score doesn't match expected value
**Solution**:
- Verify score calculation formula is correct
- Check database that responses were saved
- Ensure area weights are set correctly

## Support

For issues or questions:
1. Check the testing guide: `/docs/TESTING_ADMIN_EDIT_QUESTIONNAIRE.md`
2. Review error logs in WordPress (if available)
3. Check browser console for JavaScript errors
4. Contact system administrator

## Version History

- **v1.0** (2026-01-21): Initial release
  - Admin edit functionality
  - Score recalculation
  - CSRF protection
  - Error logging
