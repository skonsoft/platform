/*global define*/
define(['./abstract-action'
    ], function (AbstractAction) {
    'use strict';

    /**
     * Resets collection to initial state
     *
     * @export  orodatagrid/js/datagrid/action/reset-collection-action
     * @class   orodatagrid.datagrid.action.ResetCollectionAction
     * @extends orodatagrid.datagrid.action.AbstractAction
     */
    return AbstractAction.extend({

        /** @property oro.PageableCollection */
        collection: undefined,

        /**
         * Initialize action
         *
         * @param {Object} options
         * @param {oro.PageableCollection} options.collection Collection
         * @throws {TypeError} If collection is undefined
         */
        initialize: function (options) {
            var opts = options || {};

            if (!opts.datagrid) {
                throw new TypeError("'datagrid' is required");
            }
            this.collection = opts.datagrid.collection;

            AbstractAction.prototype.initialize.apply(this, arguments);
        },

        /**
         * Execute reset collection
         */
        execute: function () {
            this.collection.updateState(this.collection.initialState);
            this.collection.fetch();
        }
    });
});
