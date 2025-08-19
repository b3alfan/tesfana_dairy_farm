# Release Checklist

Before tagging a release:

1. **Update codebase**
   - Run `drush updb -y`
   - Run `drush entup`
   - `drush cr`

2. **Check coding standards**
   ```bash
   phpcs --standard=Drupal,DrupalPractice web/modules/custom/tesfana_dairy_farm
