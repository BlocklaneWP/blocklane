/**
 * The Forms screen's one presence question: does this artifact store
 * uploaded files with a submission? Its own module so that ./copy.js can
 * read the contributions index while block:form-file's file
 * (./file-upload/copy.js) asks this same question, with no import cycle
 * between the two.
 */
import { hasUnit } from '../../edition.js';

/**
 * Whether this edition stores uploaded files with a submission (the File
 * Upload field's unit is present). The screen's one presence question.
 *
 * @return {boolean} Whether file wording applies.
 */
export const keepsFiles = () => hasUnit( 'block:form-file' );
