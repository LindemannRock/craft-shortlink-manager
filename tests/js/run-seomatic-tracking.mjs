// Runs real PHP-rendered scripts with owned timers and no network/navigation.
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const input = JSON.parse(readFileSync(0, 'utf8'));
const events = [{event: 'already_queued'}];
const timeline = [];
let now = 0;
const eventTimes = [];
events.push = function (event) {
    timeline.push(event.event);
    eventTimes.push({event: event.event, time: now});
    return Array.prototype.push.call(this, event);
};
const timers = [];
const navigations = [];
const navigationTimes = [];
const context = vm.createContext({
    URLSearchParams,
    console: {log() {}},
    dataLayer: input.dataLayerName && input.dataLayerName !== 'dataLayer' ? [{event: 'default_queue_untouched'}] : events,
    location: {
        search: input.search ?? '',
        replace(url) {
            navigations.push(url);
            navigationTimes.push(now);
            timeline.push('navigate');
        },
    },
    setTimeout(callback, delay) { timers.push({callback, delay, due: now + delay}); },
});
context.window = context;
context[input.dataLayerName ?? 'dataLayer'] = events;
for (const script of input.html.matchAll(/<script>([\s\S]*?)<\/script>/g)) {
    vm.runInContext(script[1], context, {timeout: 1000});
}
const arrivalEvents = events.map(event => event.event);
const timerDelays = [];
function advanceClock(target) {
    timers.sort((a, b) => a.due - b.due);
    while (timers.length && timers[0].due <= target) {
        const timer = timers.shift();
        now = timer.due;
        timerDelays.push(timer.delay);
        timer.callback();
        timers.sort((a, b) => a.due - b.due);
    }
    now = target;
}
const checkpoints = [];
for (const time of input.checkpoints ?? []) {
    advanceClock(time);
    checkpoints.push({time, navigations: [...navigations], events: events.map(event => event.event)});
}
while (timers.length) {
    advanceClock(Math.min(...timers.map(timer => timer.due)));
}
process.stdout.write(JSON.stringify({arrivalEvents, events, timeline, navigations, timerDelays, checkpoints, eventTimes, navigationTimes, defaultEvents: context.dataLayer}));
