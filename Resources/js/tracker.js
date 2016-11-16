(function() {
    var self = lightning.tracker = {
        ready: false,
        queue: [],
        events: {
            pageView: {
                ga: 'pageview',
                fb: 'PageView',
                action: 'pageView',
            },
            viewContent: {
                fb: 'ViewContent',
            },
            search: {
                fb: 'Search',
            },
            addToCart: {
                fb: 'AddToCart',
                ga: 'event',
                category: 'Store',
                action: 'addToCart',
            },
            addToWishlist: {
                fb: 'AddToWishlist',
                ga: 'event',
                category: 'Store',
                action: 'addToWishlist',
            },
            initiateCheckout: {
                fb: 'InitiateCheckout',
                ga: 'event',
                category: 'Store',
                action: 'initiateCheckout',
            },
            addPaymentInfo: {
                fb: 'AddPaymentInfo',
                ga: 'event',
                category: 'Store',
                action: 'addPaymentInfo',
            },
            purchase: {
                fb: 'Purchase',
                ga: 'event',
                category: 'Store',
                action: 'purchase',
            },
            optin: {
                fb: 'Lead',
                ga: 'event',
                category: 'User',
                action: 'optin',
            },
            register: {
                fb: 'CompleteRegistration',
                ga: 'event',
                category: 'User',
                action: 'register',
            },
            splitTest: {
                ga: 'event',
                label: 'Split Test',
            }
        },

        /**
         * Load the tracking scripts
         */
        init: function () {
            var scripts = [];
            if (lightning.vars.google_analytics_id) {
                scripts.push('//www.google-analytics.com/analytics.js');
            }
            if (lightning.vars.facebook_pixel_id) {
                n = window.fbq = function(){
                    n.callMethod ? n.callMethod.apply(n,arguments) : n.queue.push(arguments)
                };
                if(!window._fbq) {
                    window._fbq = n;
                }
                n.push = n;
                n.loaded = true;
                n.version = '2.0';
                n.queue=[];
                scripts.push('//connect.facebook.net/en_US/fbevents.js');
            }
            if (scripts.length > 0) {
                lightning.require(scripts, function () {
                    // Init the trackers
                    if (lightning.vars.google_analytics_id) {
                        ga('create', lightning.vars.google_analytics_id, 'auto');
                    }
                    if (lightning.vars.facebook_pixel_id) {
                        fbq('init', lightning.vars.facebook_pixel_id);
                    }

                    // Track the pageview
                    self.track(self.events.pageView);
                    self.ready = true;
                    for (var i in self.queue) {
                        self.trackOnStartup(self.queue[i]);
                    }
                });
            }
        },

        trackOnStartup: function (lightningEvent) {
            if (self.ready) {
                type = lightningEvent.type;
                // If the event isn't in the list, it's going to be tracked for google only and needs an action.
                event = self.events.hasOwnProperty(type) ? self.events[type] : {action: type, ga:'event'};
                self.track(event, {
                    category: lightningEvent.category,
                    label: lightningEvent.label,
                });
            } else {
                self.queue.push(lightningEvent);
            }
        },

        /**
         * Send tracking data.
         *
         * Category and Action are required by GA.
         */
        track: function (eventType, data) {
            var trackingData = {};
            $.extend(trackingData, eventType, data);

            if (lightning.vars.google_analytics_id && trackingData.hasOwnProperty('ga')) {
                var ga_data = $.extend({
                    ccategory: null,
                    action: trackingData.fb ? trackingData.fb : trackingData.action ? trackingData.action : null,
                    label: null,
                    value: null,
                }, trackingData);
                if (lightning.vars.debug) {
                    console.log('Google Analytics Tracker: ', ga_data);
                } else {
                    ga('send', ga_data.ga,
                        ga_data.category,
                        ga_data.action,
                        ga_data.label,
                        ga_data.value
                    );
                }
            }
            if (lightning.vars.facebook_pixel_id && trackingData.hasOwnProperty('fb')) {
                if (lightning.vars.debug) {
                    console.log('Facebook Tracker: ', trackingData.fb);
                } else {
                    fbq('track', trackingData.fb);
                }
            }
            if (
                lightning.vars.google_adwords
                && trackingData.label
                && lightning.vars.google_adwords[trackingData.label]
            ) {
                var query = $.extend({}, lightning.vars.google_adwords[trackingData.label]);
                var conversion = query.conversion;
                delete query.conversion;
                var imageurl = '//www.googleadservices.com/pagead/conversion/'
                    + conversion
                    + '/?script=0&'
                    + lightning.buildQuery(query);
                if (lightning.vars.debug) {
                    console.log('Google Adwords Tracker: ' + imageurl)
                } else {
                    var image = new Image(1, 1);
                    image.src = imageurl;
                }
            }
        }
    };
})();
