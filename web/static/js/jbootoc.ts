/// <reference path="node_modules/@types/jquery/index.d.ts" />

(function($) {

    class Jbootoc {
        private readonly levels = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        private static readonly skippingSelector = 'data-jbootoc-skip';

        constructor(
            private container: JQuery,
            private scope: JQuery,
            readonly depth: number
        ) {
            this.createTableOfContents();
            this.container.attr('data-toggle', 'toc');
        }

        private createTableOfContents(): void {
            let topLevel = this.getTopLevel();
            let items = this.getItems(topLevel, this.depth);
            let navigationList = this.buildNavigationList(items);
            this.container.append(navigationList);
        }

        private getTopLevel(): number {
            for (let level in this.levels) {
                if (this.scope
                    .find(this.levels[level])
                    .filter(`:not([${Jbootoc.skippingSelector}])`)
                    .length
                ) {
                    return +level;
                }
            }
        }

        private getItems(start: number, depth: number): ListItem[] {
            let self = this;
            let items: ListItem[] = [];
            let selector = this.levels.slice(start, start + depth).join(',');
            this.scope.find(selector).each(function() {
                let item = $(this);
                items.push({
                    el: item,
                    level: self.getItemLevel(item) - start
                })
            });
            return items;
        }

        private getItemLevel(item: JQuery): number {
            for (let level in this.levels) {
                if (item.is(this.levels[level])) {
                    return +level;
                }
            }
        }

        private buildNavigationList(items: ListItem[]): JQuery {
            let list = $('<ul>').addClass('nav');
            let context = {
                el: list,
                level: 0,
            };
            let contextBag: ListItem[] = [];

            for (let i in items) {
                let item = items[i];

                while (context.level != item.level) {
                    if (context.level < item.level) {
                        contextBag.push(context);
                        context = {
                            el: $('<ul>').addClass('nav').appendTo(context.el.children('li').last()),
                            level: context.level + 1,
                        };
                    } else {
                        context = contextBag.pop();
                    }
                }

                context.el.append(this.generateListItem(item.el));
            }

            return list;
        }

        private generateListItem(item: JQuery): JQuery {
            let link = $('<a>')
                .prop('href', '#' + this.generateAnchor(item))
                .text(this.getItemText(item));
            return $('<li>').append(link);
        }

        private generateAnchor(item: JQuery): string {
            if (item.prop('id')) {
                return item.prop('id');
            }

            let anchorBase = item.data('jbootocId') || item.text();
            anchorBase = anchorBase.trim().toLowerCase().replace(/\W+/g, '-');
            let anchor = anchorBase;
            let i = 0;
            while (document.getElementById(anchor)) {
                anchor = anchorBase + '-' + (++i);
            }
            item.prop('id', anchor);
            return anchor;
        }

        private getItemText(item: JQuery): string {
            return item.data('jbootocText') || item.text();
        }
    }

    interface ListItem {
        el: JQuery;
        level: number;
    }

    interface JbootocOptions {
        scope?: JQuery,
        depth?: number,
    }

    $.fn.jbootoc = function(options: JbootocOptions = {}) {
        let scope = options.scope || $(document);
        let depth = options.depth || 2;
        let jbootoc = new Jbootoc(this, scope, depth);
    };

})(jQuery);
