# Admin Questionnaire Editing Feature - Testing Guide

## Overview
This document provides testing instructions for the new admin questionnaire editing functionality.

## Feature Description
Admins can now edit completed questionnaire responses from the "Storico Invii" (Submissions) tab. The system will:
1. Load the questionnaire with current answers pre-filled
2. Allow modification of any answer
3. Automatically recalculate the score
4. Display the updated score with stars and evaluation

## Files Changed
- `bo-questionnaires.php`: Added edit button, modal, and JavaScript functions
- `ajax_fornitori/get_editable_questionnaire.php`: New endpoint to load editable form
- `ajax_fornitori/save_questionnaire_edits.php`: New endpoint to save edits and recalculate score

## Prerequisites
1. WordPress site with the Cogei questionnaire system installed
2. Administrator account with login credentials
3. At least one completed questionnaire in the system

## Test Cases

### 1. Admin-Only Access
**Objective**: Verify only administrators can edit questionnaires

**Steps**:
1. Log in as an administrator
2. Navigate to the Questionari admin page
3. Click on the "Storico Invii" tab
4. Verify the "✏️ Modifica Risposte" button is visible for completed questionnaires

**Expected Result**: 
- Button is visible and functional for admin users
- Non-admin users should not see the button (test with subscriber/HSE role if possible)

### 2. Load Editable Questionnaire
**Objective**: Verify the questionnaire loads correctly with current answers

**Steps**:
1. As admin, navigate to "Storico Invii" tab
2. Find a completed questionnaire
3. Click the "✏️ Modifica Risposte" button
4. Wait for the modal to load

**Expected Result**:
- Modal opens with the questionnaire title
- All questions are displayed
- Current answers are pre-selected (highlighted in blue)
- Required questions are marked with asterisk (*)
- N.A. options show yellow badge
- All areas are displayed with their weights

### 3. Edit and Save Responses
**Objective**: Verify editing and saving works correctly

**Steps**:
1. Open a questionnaire for editing (see Test Case 2)
2. Change at least 3 answers in different areas
3. Click "✓ Salva Modifiche" button
4. Wait for the success message

**Expected Result**:
- Success message appears with green checkmark
- New score is displayed (0-100 scale)
- Star rating is shown (0-5 stars)
- Evaluation level is displayed (Eccellente, Molto Buono, Adeguato, Critico, Inadeguato)
- Message confirms score was recalculated

### 4. Verify Score Recalculation
**Objective**: Verify the score is correctly recalculated

**Steps**:
1. Note the original score before editing (from "Visualizza Risultato")
2. Edit the questionnaire and change some high-weight answers to low-weight
3. Save the changes
4. Note the new score
5. Close the modal and refresh the page
6. Click "Visualizza Risultato" for the same questionnaire

**Expected Result**:
- New score should be different from original score
- Score should reflect the changes made (lower if changed to lower-weight options)
- The score in the results page should match the score shown in the success message
- Star rating and evaluation should be consistent with the score

### 5. Required Fields Validation
**Objective**: Verify required questions cannot be left unanswered

**Steps**:
1. Open a questionnaire for editing
2. Find a required question (marked with *)
3. Try to save without selecting an option for that question

**Expected Result**:
- Browser validation prevents form submission
- Alert message appears: "Per favore, rispondi a tutte le domande obbligatorie."
- Form is not submitted until all required questions are answered

### 6. N.A. Response Handling
**Objective**: Verify N.A. responses use maximum weight

**Steps**:
1. Open a questionnaire for editing
2. Find a question with an N.A. option
3. Select the N.A. option
4. Save the changes
5. Check the new score

**Expected Result**:
- N.A. option shows yellow "N.A." badge
- Score calculation uses the maximum weight for that question
- Score should be higher or equal compared to selecting a low-weight option

### 7. CSRF Protection
**Objective**: Verify CSRF protection is working

**Steps**:
1. Open browser developer tools
2. Go to Network tab
3. Open a questionnaire for editing
4. Inspect the AJAX request to `get_editable_questionnaire.php`
5. Verify a `nonce` parameter is included
6. Save changes and inspect the AJAX request to `save_questionnaire_edits.php`
7. Verify a `nonce` parameter is included

**Expected Result**:
- Both AJAX requests include a `nonce` parameter
- Requests without valid nonce should be rejected with 403 error

### 8. Multiple Edits
**Objective**: Verify questionnaire can be edited multiple times

**Steps**:
1. Edit a questionnaire and save changes
2. Close the modal
3. Open the same questionnaire for editing again
4. Verify new answers are pre-selected
5. Make additional changes
6. Save again

**Expected Result**:
- Second edit loads with the answers from first edit
- Score continues to update correctly
- No errors occur

### 9. Close Without Saving
**Objective**: Verify changes are not saved when modal is closed

**Steps**:
1. Open a questionnaire for editing
2. Change some answers
3. Click the × button to close modal (without saving)
4. Open the questionnaire for editing again

**Expected Result**:
- Original answers are still selected
- Changes made in step 2 are not saved

### 10. Error Handling
**Objective**: Verify error handling works correctly

**Steps**:
1. Open browser developer tools
2. Temporarily disable network (or block the AJAX endpoint)
3. Try to open a questionnaire for editing

**Expected Result**:
- Error message is displayed: "Errore: Impossibile caricare il questionario"
- No JavaScript errors in console
- User can close the modal and try again

## Performance Tests

### Load Time
**Objective**: Verify the modal loads in reasonable time

**Steps**:
1. Open a questionnaire with many questions (20+)
2. Time how long it takes for the modal to fully load

**Expected Result**:
- Modal should load within 2-3 seconds on normal connection
- Loading spinner is shown during the wait

### Save Time
**Objective**: Verify saving completes in reasonable time

**Steps**:
1. Edit a large questionnaire (20+ questions)
2. Change multiple answers
3. Time how long it takes to save

**Expected Result**:
- Save should complete within 3-5 seconds
- Loading spinner is shown during the save

## Security Tests

### Non-Admin Access
**Objective**: Verify non-admins cannot access edit endpoints

**Steps**:
1. Log out or use a non-admin account
2. Try to directly access the AJAX endpoints via browser or tool:
   - `POST /ajax_fornitori/get_editable_questionnaire.php?assignment_id=1`
   - `POST /ajax_fornitori/save_questionnaire_edits.php?assignment_id=1&responses={...}`

**Expected Result**:
- Both endpoints return 403 Forbidden
- Error message: "Accesso negato. Solo gli amministratori possono modificare i questionari."

### Invalid Nonce
**Objective**: Verify invalid nonces are rejected

**Steps**:
1. Using browser developer tools or Postman
2. Make a request with invalid nonce value
3. Check the response

**Expected Result**:
- Endpoint returns 403 Forbidden
- Error message: "Errore di sicurezza. Token non valido."

## Database Verification

### Check Updated Responses
**Objective**: Verify database is updated correctly

**Steps**:
1. Note a questionnaire assignment ID (e.g., assignment_id = 5)
2. Check database before editing:
   ```sql
   SELECT * FROM wp_cogei_responses WHERE assignment_id = 5;
   ```
3. Edit the questionnaire and save
4. Check database after editing with same query

**Expected Result**:
- `selected_option_id` is updated for changed questions
- `computed_score` is updated for changed questions
- `answered_at` timestamp is updated

### Score Consistency
**Objective**: Verify score calculation is consistent

**Steps**:
1. Manually calculate expected score using formula:
   - For each area: area_score = (sum of question weights) × area_weight
   - Total score = sum of area_scores × 100
2. Compare with displayed score

**Expected Result**:
- Displayed score matches manual calculation
- Score is between 0 and 100

## Browser Compatibility

Test the feature on:
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (if available)

## Regression Tests

Verify existing functionality still works:
- [ ] Creating new questionnaires
- [ ] Sending questionnaires via email
- [ ] Completing questionnaires (public form)
- [ ] Viewing completed questionnaires results
- [ ] The "Visualizza Risultato" button still works

## Issues and Bugs

Document any issues found during testing:

| Test Case | Issue Description | Severity | Status |
|-----------|------------------|----------|--------|
| | | | |

## Sign-Off

- [ ] All test cases passed
- [ ] No critical bugs found
- [ ] Feature is ready for production

**Tested by**: ___________________  
**Date**: ___________________  
**Signature**: ___________________
