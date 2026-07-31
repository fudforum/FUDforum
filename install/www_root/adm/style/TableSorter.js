/**
 * TableSorter - A vanilla JavaScript class for sorting HTML tables
 *
 * @version 2.0.0
 * @license GNU
 *
 *
 * QUICK START
 * -----------
 * 1. Include the CSS and JS:
 *    <link rel="stylesheet" href="path/to/tableSorter.css">
 *    <script src="path/to/TableSorter.js"></script>
 *
 * 2. Add data-sortable to your table
 * 3. Add data-sort-type to each <th> (string, number, range-min, etc.)
 *
 * Example:
 *   <table data-sortable>
 *     <th data-sort-type="string">Name</th>
 *     <th data-sort-type="number">Price</th>
 *   </table>
 *
 *
 * CSS
 * ---
 * Include tableSorter.css for essential styling:
 * - Sort indicators (▲/▼/⇅) via pseudo-elements
 * - Cursor: pointer on sortable headers
 * - Hover effects for better UX
 *
 * <link rel="stylesheet" href="path/to/tableSorter.css">
 *
 * The JS injects these classes you can use for custom styling:
 * .sortable - Added to all sortable headers (use for cursor, hover effects)
 * .sort-asc - Added to currently sorted column (ascending)
 * .sort-desc - Added to currently sorted column (descending)
 *
 * The included CSS is minimal and designed to be overridden.
 * Use these classes as hooks for your own styles.
 *
 *
 * ATTRIBUTES
 * ----------
 * data-sortable
 *   (On <table>) Enables sorting. Tables without this are ignored.
 *
 * data-sort-type (required on sortable columns)
 *   Specifies how to sort this column. Can be any registered comparator name.
 *   Built-in: "string", "string-case-sensitive", "number", "range-min", "range-max"
 *   Custom: Any name registered via TableSorter.registerComparator()
 *
 * data-sort-type-desc (optional)
 *   Specifies a different comparator for descending sorts. If not provided,
 *   descending simply reverses the ascending comparator.
 *   Example: <th data-sort-type="range-min" data-sort-type-desc="range-max">
 *
 * data-default-sort-direction (optional)
 *   Sets the direction when this column is first clicked (not toggling).
 *   Values: "ascending" (default) or "descending"
 *   Example: <th data-sort-type="number" data-default-sort-direction="descending">
 *
 * data-sortable="false" (optional on <th>)
 *   Explicitly marks a column as non-sortable. No click handler, no sort indicator.
 *
 *
 * CUSTOM COMPARATORS
 * ------------------
 * TableSorter.registerComparator(name, definition)
 * 
 * Definition can be:
 * - A function (a, b) => number  (simple comparator, assumes all values valid)
 * - An object with:
 *   - compare: (a, b) => number  (required)
 *   - isValid: (cellValue) => boolean  (optional, defaults to true)
 *
 * Examples:
 *
 * // Simple custom comparator (assumes all values valid)
 * TableSorter.registerComparator('file-size', (a, b) => {
 *     // Convert "10 KB", "2 MB" etc. to bytes for comparison
 *     const toBytes = (str) => {
 *         const [_, num, unit] = str.match(/(\d+)\s*(KB|MB|GB)/i) || [];
 *         if (!num) return 0;
 *         const multiplier = { KB: 1024, MB: 1048576, GB: 1073741824 }[unit.toUpperCase()];
 *         return parseInt(num, 10) * multiplier;
 *     };
 *     return toBytes(a) - toBytes(b);
 * });
 *
 * // Custom comparator with validation
 * TableSorter.registerComparator('priority', {
 *     compare: (a, b) => {
 *         const order = { high: 3, medium: 2, low: 1 };
 *         return (order[a.toLowerCase()] || 0) - (order[b.toLowerCase()] || 0);
 *     },
 *     isValid: (val) => ['high', 'medium', 'low'].includes(val.toLowerCase())
 * });
 *
 *
 * MANUAL INITIALIZATION
 * ---------------------
 * const sorter = new TableSorter(tableElement);
 * sorter.sort(columnIndex, ascending);
 * sorter.reset();
 *
 *
 * AUTO-INITIALIZATION
 * -------------------
 * Runs automatically on DOMContentLoaded for all tables with data-sortable.
 * To prevent auto-init on a specific table, remove the attribute and initialize manually.
 *
 *
 * BEHAVIOR NOTES
 * --------------
 * - Single column sorting only (no multi-column)
 * - Click same column toggles asc/desc
 * - Click different column starts fresh (respects data-default-sort-direction)
 * - Invalid cells (failing isValid) always sort to bottom, regardless of direction
 * - Console warnings for missing attributes, invalid values, or parsing issues
 */

(function(global) {
    'use strict';

    /**
     * Static registry of built-in comparators.
     * Each comparator receives two cell values (as strings) and returns:
     *   - negative if a < b
     *   - positive if a > b
     *   - zero if equal
     */
    const BUILT_IN_COMPARATORS = {
        /**
         * Case-insensitive string comparison.
         */
        string: {
            compare: (a, b) => String(a).toLowerCase().localeCompare(String(b).toLowerCase()),
            isValid: () => true // All strings are valid
        },

        /**
         * Case-sensitive string comparison.
         */
        'string-case-sensitive': {
            compare: (a, b) => String(a).localeCompare(String(b)),
            isValid: () => true
        },

        /**
         * Numeric comparison. Non-numeric or empty values are pushed to the bottom (Infinity).
         * Logs a warning for non-empty cells that fail parsing.
         */
        number: {
            compare: (a, b) => {
                const numA = _parseNumber(a);
                const numB = _parseNumber(b);
                return (numA - numB);
            },
            isValid: (val) => {
                const str = String(val).trim();
                if (str === '') return false;
                return !isNaN(parseFloat(str));
            }
        },

        /**
         * Range number comparison. Extracts the first number from strings like "4-5" or "4–5 minutes".
         * Non-numeric or empty values are pushed to the bottom (Infinity).
         * Logs a warning for non-empty cells that fail parsing.
         */
        'range-min': {
            compare: (a, b) => {
                const numA = _parseRangeNumber(a);
                const numB = _parseRangeNumber(b);
                return (numA - numB);
            },
            isValid: (val) => _validRange(val)
        },


        /**
         * Range number comparison. Extracts the second number from strings like "4-5" or "4–5 minutes".
         * Non-numeric or empty values are pushed to the bottom (Infinity).
         * Logs a warning for non-empty cells that fail parsing.
         */
        'range-max': {
            compare: (a, b) => {
                const numA = _parseRangeNumber(a, true);
                const numB = _parseRangeNumber(b, true);
                return (numA - numB);
            },
            isValid: (val) => _validRange(val)
        }
    };

    /**
     * Helper: Parse a number from a cell value.
     * Returns Infinity for unparseable values (except empty cells, which return Infinity silently).
     * @param {string} val - The cell text content
     * @returns {number} Parsed number or Infinity
     * @private
     */
    function _parseNumber(val) {
        const str = String(val).trim();
        if (str === '') return Infinity; // Empty cells: bottom, no warning

        const num = parseFloat(str);
        if (isNaN(num)) {
            console.warn(`TableSorter: Could not parse value as number: "${str}"`);
            return Infinity;
        }
        return num;
    }

    /**
     * Helper: Parse either first or second number from a range string.
     * Returns Infinity for unparseable values (except empty cells, which return Infinity silently).
     * @param {string} val - The cell text content
     * @param {boolean} getMax - true if want second number, false if want first
     * @returns {number} First number found or Infinity
     * @private
     */
    function _parseRangeNumber(val, getMax = false) {
        const str = String(val).trim();
        if (str === '') return Infinity; // Empty cells: bottom, no warning

        const matches = str.match(/\d+/g);
        if (!matches) {
            console.warn(`TableSorter: Could not parse range number from: "${str}"`);
            return Infinity;
        }

        const min = parseInt(matches[0], 10);
        // If only one number, min and max are the same
        const max = matches.length > 1 ? parseInt(matches[1], 10) : min;

        if (getMax) {
            return max;
        }
        return min;
    }

    /**
     * Helper: Return true/false if a range is valid.
     */
    function _validRange(val) {
        const str = String(val).trim();
        if (str === '') return false;
        return /\d+/.test(str);
    }

    /**
     * TableSorter class.
     * @param {HTMLTableElement} tableElement - The table to make sortable
     */
    global.TableSorter = class TableSorter {
        /**
         * Registered custom comparators.
         * @type {Object.<string, Function>}
         */
        static customComparators = {};

        /**
         * Register a custom comparator.
         * @param {string} name - Unique identifier for the comparator
         * @param {Function} fn - Comparator function (a, b) => number
         */
        static registerComparator(name, definition) {
            // Allow simple function for backward compatibility
            if (typeof definition === 'function') {
                definition = {
                    compare: definition,
                    isValid: () => true // Default: all values valid
                };
            }

            if (typeof definition.compare !== 'function') {
                console.error(`TableSorter: Comparator "${name}" must have a compare function`);
                return;
            }

            if (typeof definition.isValid !== 'function') {
                console.warn(`TableSorter: Comparator "${name}" missing isValid, defaulting to all valid`);
                definition.isValid = () => true;
            }

            TableSorter.customComparators[name] = definition;
        }

        /**
         * Create a TableSorter instance.
         * @param {HTMLTableElement} tableElement - The table element to enhance
         */
        constructor(tableElement) {
            // Validate input
            if (!tableElement || tableElement.tagName !== 'TABLE') {
                console.error('TableSorter: Invalid table element provided');
                return;
            }

            this.table = tableElement;
            this.headers = [];
            this.dataRows = [];
            this.originalRows = []; // Store for reset functionality
            this.currentSort = {
                column: -1,
                ascending: true
            };

            // Initialize if table has valid structure
            this._init();
        }

        /**
         * Initialize the table sorter.
         * @private
         */
        _init() {
            // Locate header row and data rows
            const headerResult = this._locateHeaders();
            if (!headerResult) {
                console.warn('TableSorter: Could not locate headers, skipping table');
                return;
            }

            this.headers = headerResult.headers;

            // Store original rows for reset
            const allRows = Array.from(this.table.rows);
            const firstDataRowIndex = headerResult.dataStartIndex;
            this.originalRows = allRows.slice(firstDataRowIndex);
            this.dataRows = [...this.originalRows]; // Start with original order

            // Set up each header
            this.headers.forEach((header, index) => {
                this._setupHeader(header.element, index);
            });

            // Add .sortable class to headers (for css convenience) UNLESS
            // 'data-sortable=false' present (data-sortable=false detected in
            // _locateHeaders(), .isSortable attr added to header objects as a result)
            // This class addition can be removed if you prefer users to style via [data-sort-type] instead
            this.headers
                .filter(h => h.isSortable)
                .forEach(h => h.element.classList.add('sortable'));
        }

        /**
         * Locate header row and data rows.
         * Strategy: Find first row containing <th> elements. If none found, fall back to first row with warning.
         * @returns {Object|null} { headers: Array<{element, sortType}>, dataStartIndex: number } or null if failed
         * @private
         */
        _locateHeaders() {
            const rows = Array.from(this.table.rows);
            if (rows.length === 0) {
                console.warn('TableSorter: Table has no rows');
                return null;
            }

            // Find first row with <th> elements
            let headerRowIndex = -1;
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].cells;
                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].tagName === 'TH') {
                        headerRowIndex = i;
                        break;
                    }
                }
                if (headerRowIndex !== -1) break;
            }

            // Fallback to first row if no <th> found
            if (headerRowIndex === -1) {
                console.warn('TableSorter: No <th> elements found, using first row as header');
                headerRowIndex = 0;
            }

            const headerRow = rows[headerRowIndex];
            const headerCells = Array.from(headerRow.cells);

            // Check for colspan/rowspan (we don't support these currently)
            const hasComplexHeaders = headerCells.some(cell => cell.colSpan > 1 || cell.rowSpan > 1);
            if (hasComplexHeaders) {
                console.warn('TableSorter: Table has colspan or rowspan in headers, skipping (unsupported)');
                return null;
            }

            // Build header objects
            const headers = headerCells.map(cell => {
                // Check if column is explicitly non-sortable
                const isSortable = cell.getAttribute('data-sortable') !== 'false';
                let sortType = cell.getAttribute('data-sort-type');
                let sortTypeDesc = cell.getAttribute('data-sort-type-desc') || sortType; // Fall back to same if not specified

                if (!isSortable) {
                    sortType = null; // Mark as non-sortable
                } else if (!sortType) {
                    // Default to string with warning
                    console.warn(`TableSorter: No data-sort-type on header "${cell.textContent.trim()}", defaulting to "string"`);
                    sortType = 'string';
                }

                return {
                    element: cell,
                    sortType: sortType,
                    sortTypeDesc: sortTypeDesc,
                    isSortable: isSortable
                };
            });

            return {
                headers: headers,
                dataStartIndex: headerRowIndex + 1
            };
        }

        /**
         * Set up click handler on a header.
         * @param {HTMLElement} header - The header element
         * @param {number} index - Column index
         * @private
         */
        _setupHeader(header, index) {
            // Skip if column is not sortable
            if (!this.headers[index].isSortable) return;

            // Remove any existing listener to avoid duplicates
            header.removeEventListener('click', this._headerClickHandler);

            // Bind the handler to this instance
            const handler = this._createHeaderClickHandler(index);
            header.addEventListener('click', handler);

            // Store for potential cleanup (though not needed in basic usage)
            header._sorterHandler = handler;
        }

        /**
         * Creates a click handler for a specific column.
         *
         * The handler determines sort direction as follows:
         * - If this column is already the current sort column → toggle direction
         *   (ascending → descending or descending → ascending)
         * - If a different column was sorted previously → start fresh with
         *   col's default sort direction (ascending, unless specified otherwise
         *   by 'data-default-sort-direction=descending' in <th>)
         *
         * Why start fresh with ascending for new columns?
         * Once another column is sorted, the previous column's rows are
         * no longer in any meaningful order. "Remembering" the last direction
         * would be misleading. Starting with ascending provides a consistent baseline.
         *
         * @param {number} columnIndex - The column this handler is for
         * @returns {Function} Click event handler
         * @private
         */
        _createHeaderClickHandler(columnIndex) {
            return (event) => {
                event.preventDefault();

                // determine sort order
                let ascending = true;
                if (this.currentSort.column === columnIndex) {
                    // Same column clicked consecutively: toggle direction
                    ascending = !this.currentSort.ascending;
                } else {
                    // Different column clicked before this one:
                    // use column's default sort direction --
                    // ascending unless otherwise specified by data-sort-direction
                    ascending = this._getDefaultSortDir(columnIndex);
                }

                this.sort(columnIndex, ascending);
            };
        }

        /**
         * Gets the default sort direction for a column from its data attribute.
         * @param {number} columnIndex - The column index
         * @returns {boolean} True for ascending, false for descending
         * @private
         */
        _getDefaultSortDir(columnIndex) {
            const header = this.headers[columnIndex];
            const defaultDir = header.element.getAttribute('data-default-sort-direction');

            if (!defaultDir) return true; // No attribute = ascending

            const normalized = defaultDir.toLowerCase().trim();
            if (normalized === 'descending') return false;
            if (normalized === 'ascending') return true;

            // Invalid value
            console.warn(
                `TableSorter: Invalid data-default-sort-direction "${defaultDir}" on column "${header.element.textContent.trim()}". ` +
                `Expected "ascending" or "descending". Defaulting to ascending.`
            );
            return true;
        }

        /**
         * Sort the table by a specific column.
         * @param {number} columnIndex - Column to sort by
         * @param {boolean} ascending - True for ascending, false for descending
         */
        sort(columnIndex, ascending = true) {
            // Validate column index
            if (columnIndex < 0 || columnIndex >= this.headers.length) {
                console.error(`TableSorter: Invalid column index ${columnIndex}`);
                return;
            }

            const header = this.headers[columnIndex];
            if (!header.isSortable) {
                console.warn(`TableSorter: Attempted to sort non-sortable column ${columnIndex}`);
                return;
            }

            // Get comparator for this column
            const comparatorDef = this._getComparator(header, ascending);
            const comparator = comparatorDef.compare;
            const isValidCell = comparatorDef.isValid;

            // Sort data rows
            this.dataRows.sort((rowA, rowB) => {
                const cellA = rowA.cells[columnIndex]?.textContent || '';
                const cellB = rowB.cells[columnIndex]?.textContent || '';

                const result = comparator(cellA, cellB);
                return ascending ? result : -result;
            });

            // After sorting, ensure invalid rows are at the bottom
            // This is especially important for descending sort
            const invalidRows = [];
            const validRows = [];

            this.dataRows.forEach(row => {
                const cellValue = row.cells[columnIndex]?.textContent || '';
                if (isValidCell(cellValue)) {
                    validRows.push(row);
                } else {
                    invalidRows.push(row);
                }
            });

            this.dataRows = [...validRows, ...invalidRows];

            // Reattach rows to tbody (or directly to table if no tbody)
            this._renderRows();

            // Update sort state and indicators
            this.currentSort = {
                column: columnIndex,
                ascending
            };
            this._updateIndicators();
        }

        /**
         * Reset table to original unsorted order.
         */
        reset() {
            this.dataRows = [...this.originalRows];
            this._renderRows();
            this.currentSort = {
                column: -1,
                ascending: true
            };
            this._updateIndicators();
        }

        /**
         * Render the current data rows to the DOM.
         * Handles tables with or without tbody.
         * @private
         */
        _renderRows() {
            // Find or create tbody
            let tbody = this.table.querySelector('tbody');
            if (!tbody) {
                // If no tbody, wrap all rows in a new tbody
                tbody = document.createElement('tbody');
                const rows = Array.from(this.table.rows);
                rows.forEach(row => tbody.appendChild(row));
                this.table.appendChild(tbody);
            } else {
                // Clear existing rows
                tbody.innerHTML = '';
            }

            // Append sorted rows
            this.dataRows.forEach(row => tbody.appendChild(row));
        }

        /**
         * Get the appropriate comparator function for a sort type.
         * @param {Object} header - The header object
         *  (see _locateHeaders for source of truth on headers)
         * @param {boolean} ascending - true if ascending sort, false if descending
         *  (user can define different comparators for each direction)
         * @returns {Function} Comparator function
         * @private
         */
        _getComparator(header, ascending) {
            const sortType = ascending ? header.sortType : header.sortTypeDesc;

            // Handle custom comparators
            if (TableSorter.customComparators[sortType]) {
                return TableSorter.customComparators[sortType];
            }

            // Built-in comparators
            if (BUILT_IN_COMPARATORS[sortType]) {
                return BUILT_IN_COMPARATORS[sortType];
            }

            // Fallback
            console.warn(`TableSorter: Unknown sort type "${sortType}", defaulting to string`);
            return BUILT_IN_COMPARATORS.string;
        }

        /**
         * Update sort indicator classes on headers.
         * @private
         */
        _updateIndicators() {
            // Remove indicators from all headers
            this.headers.forEach(header => {
                header.element.classList.remove('sort-asc', 'sort-desc');
            });

            // Add indicator to current sort column
            if (this.currentSort.column !== -1) {
                const activeHeader = this.headers[this.currentSort.column];
                activeHeader.element.classList.add(this.currentSort.ascending ? 'sort-asc' : 'sort-desc');
            }
        }
    };

    // Auto-initialize on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', () => {
        const tables = document.querySelectorAll('table[data-sortable]');
        tables.forEach(table => {
            // Avoid double-initialization
            if (!table._tableSorter) {
                table._tableSorter = new global.TableSorter(table);
            }
        });
    });

})(window);