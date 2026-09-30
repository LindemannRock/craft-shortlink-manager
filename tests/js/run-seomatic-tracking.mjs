// Runs real PHP-rendered scripts with owned timers and no network/navigation.
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const input = JSON.parse(readFileSync(0, 'utf8'));
const events = [{event: 'already_queued'}];
const timeline = [];
events.push = function (event) {
    timeline.push(event.event);
    return Array.prototype.push.call(this, event);
};
const timers = [];
const navigations = [];
const context = vm.createContext({
    URLSearchParams,
    console: {log() {}},
    dataLayer: events,
    location: {
        search: input.search ?? '',
        replace(url) { navigations.push(url); timeline.push('navigate'); },
    },
    setTimeout(callback, delay) { timers.push({callback, delay}); },
});
context.window = context;
for (const script of input.html.matchAll(/<script>([\s\S]*?)<\/script>/g)) {
    vm.runInContext(script[1], context, {timeout: 1000});
}
const arrivalEvents = events.map(event => event.event);
const timerDelays = [];
while (timers.length) {
    const timer = timers.shift();
    timerDelays.push(timer.delay);
    timer.callback();
}
process.stdout.write(JSON.stringify({arrivalEvents, events, timeline, navigations, timerDelays}));
