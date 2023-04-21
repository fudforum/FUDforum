/*!
 * jQuery keepAlive plugin
 * https://github.com/Aldri/jQuery-keepAlive
 *
 * Copyright 2011, Olivier Combe
 * Dual licensed under the MIT or GPL Version 2 licenses.
 * http://jquery.org/license
 *
 * Version 1.0 2011-02-17
 */
(function($) {
    var methods = {
        init : function(options) {
            var defaults = {
                url: "keepAlive.php",
                timer: 600000 // 10min
            };
            this.options = $.extend(defaults, options);
            methods._poke.apply(this);
            return this;
        },
        stop : function() {
            if(this.nextPoke)
                clearTimeout(this.nextPoke);
        },
        _poke : function() {
            var self = this;
            this.nextPoke = setTimeout(function() {
                $.ajax({
                    url: self.options.url,
                    cache: false
                });
                methods._poke.apply(self);
            }, self.options.timer);
        }
    };

    $.fn.keepAlive = function(method) {
        if (methods[method]) {
            return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof method === 'object' || !method) {
            return methods.init.apply(this, arguments);
        } else {
            $.error("The method " +  method + " doesn't exist in $.fn.keepAlive");
        }
    };
})(jQuery);
