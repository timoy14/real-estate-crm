/** The label of `value` in a server-sent option list; callers pick their own fallback. */
export function optionLabel(options, value) {
    return options.find((o) => o.value === value)?.label;
}
