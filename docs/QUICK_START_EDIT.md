# Quick Start: Editing Completed Questionnaires

## For Administrators

### How to Edit a Completed Questionnaire

1. **Navigate to the Admin Panel**
   - Log in to WordPress as an administrator
   - Go to the Questionari section

2. **Find the Questionnaire**
   - Click on the "Storico Invii" tab
   - Look for the completed questionnaire you want to edit
   - You'll see a table with all sent questionnaires

3. **Open the Editor**
   - Find the orange "✏️ Modifica Risposte" button
   - It's located below the "📊 Visualizza Risultato" link
   - Click the button to open the editing modal

4. **Edit the Answers**
   - The modal shows all questions with current answers pre-selected (blue highlight)
   - Click on any option to change the answer
   - Required questions are marked with a red asterisk (*)
   - You can see the weight of each option on the right

5. **Save Your Changes**
   - Click the green "✓ Salva Modifiche" button at the bottom
   - Wait for the system to save (shows loading spinner)
   - You'll see a success message with the new score

6. **View Updated Results**
   - Click the "Chiudi" button
   - The page will refresh automatically
   - Click "📊 Visualizza Risultato" to see the full updated evaluation

## Key Features

### Score Display
After saving, you'll see:
- **Score**: 0-100 scale (e.g., 85.50 / 100)
- **Stars**: 0-5 stars rating (e.g., ★★★★☆)
- **Evaluation**: Text rating
  - Eccellente (85-100)
  - Molto Buono (70-84)
  - Adeguato (55-69)
  - Critico (40-54)
  - Inadeguato (0-39)

### N.A. Responses
- Options marked with a yellow "N.A." badge
- These are "Not Applicable" responses
- Automatically use the maximum weight for that question

### Required Questions
- Marked with a red asterisk (*)
- Must be answered before you can save
- Browser will alert you if you try to save without answering

## Tips

✅ **Do:**
- Review all questions before saving
- Check the new score to ensure it reflects your changes
- Note that changes are immediate and cannot be undone (unless you edit again)

❌ **Don't:**
- Close the modal without saving if you want to keep your changes
- Edit multiple questionnaires simultaneously (edit one at a time)
- Forget to refresh the results page after editing

## Troubleshooting

### Button Not Visible?
- Make sure the questionnaire status is "completed"
- Verify you're logged in as an administrator
- Try refreshing the page

### Can't Save Changes?
- Check that all required questions (marked with *) are answered
- Make sure you're still logged in
- Try refreshing the page and editing again

### Wrong Score?
- Double-check that you selected the correct options
- Remember: higher weight = better score
- N.A. options use the maximum weight

## Need Help?

For detailed information:
- **Full Documentation**: See `docs/ADMIN_EDIT_FEATURE.md`
- **Testing Guide**: See `docs/TESTING_ADMIN_EDIT_QUESTIONNAIRE.md`
- **Technical Support**: Contact your system administrator

---

**Last Updated**: January 21, 2026  
**Version**: 1.0
