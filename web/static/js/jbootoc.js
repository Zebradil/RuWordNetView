(function ($) {
    var Jbootoc = (function () {
        function Jbootoc(container, scope, depth) {
            this.container = container;
            this.scope = scope;
            this.depth = depth;
            this.levels = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
            this.createTableOfContents();
            this.container.attr('data-toggle', 'toc');
        }
        Jbootoc.prototype.createTableOfContents = function () {
            var topLevel = this.getTopLevel();
            var items = this.getItems(topLevel, this.depth);
            var navigationList = this.buildNavigationList(items);
            this.container.append(navigationList);
        };
        Jbootoc.prototype.getTopLevel = function () {
            for (var level in this.levels) {
                if (this.scope
                    .find(this.levels[level])
                    .filter(":not([" + Jbootoc.skippingSelector + "])")
                    .length) {
                    return +level;
                }
            }
        };
        Jbootoc.prototype.getItems = function (start, depth) {
            var self = this;
            var items = [];
            var selector = this.levels.slice(start, start + depth).join(',');
            this.scope.find(selector).each(function () {
                var item = $(this);
                items.push({
                    el: item,
                    level: self.getItemLevel(item) - start
                });
            });
            return items;
        };
        Jbootoc.prototype.getItemLevel = function (item) {
            for (var level in this.levels) {
                if (item.is(this.levels[level])) {
                    return +level;
                }
            }
        };
        Jbootoc.prototype.buildNavigationList = function (items) {
            var list = $('<ul>').addClass('nav');
            var context = {
                el: list,
                level: 0,
            };
            var contextBag = [];
            for (var i in items) {
                var item = items[i];
                while (context.level != item.level) {
                    if (context.level < item.level) {
                        contextBag.push(context);
                        context = {
                            el: $('<ul>').addClass('nav').appendTo(context.el.children('li').last()),
                            level: context.level + 1,
                        };
                    }
                    else {
                        context = contextBag.pop();
                    }
                }
                context.el.append(this.generateListItem(item.el));
            }
            return list;
        };
        Jbootoc.prototype.generateListItem = function (item) {
            var link = $('<a>')
                .prop('href', '#' + this.generateAnchor(item))
                .text(this.getItemText(item));
            return $('<li>').append(link);
        };
        Jbootoc.prototype.generateAnchor = function (item) {
            if (item.prop('id')) {
                return item.prop('id');
            }
            var anchorBase = item.data('jbootocId') || item.text();
            anchorBase = anchorBase.trim().toLowerCase().replace(/\W+/g, '-');
            var anchor = anchorBase;
            var i = 0;
            while (document.getElementById(anchor)) {
                anchor = anchorBase + '-' + (++i);
            }
            item.prop('id', anchor);
            return anchor;
        };
        Jbootoc.prototype.getItemText = function (item) {
            return item.data('jbootocText') || item.text();
        };
        return Jbootoc;
    }());
    Jbootoc.skippingSelector = 'data-jbootoc-skip';
    $.fn.jbootoc = function (options) {
        if (options === void 0) { options = {}; }
        var scope = options.scope || $(document);
        var depth = options.depth || 2;
        var jbootoc = new Jbootoc(this, scope, depth);
    };
})(jQuery);
