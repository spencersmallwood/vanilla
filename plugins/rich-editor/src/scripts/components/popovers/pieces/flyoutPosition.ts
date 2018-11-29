/*
 * @author Stéphane LaFlèche <stephane.l@vanillaforums.com>
 * @copyright 2009-2018 Vanilla Forums Inc.
 * @license GPL-2.0-only
 */

/*
 * Utility function to get positioning of flyout based on props.
 * @param renderAbove - Render flyout above button
 * @param renderLeft - Render flyout towards left
 * @ignore ignore - Do nothing - for legacy
 */
export function flyoutPosition(renderAbove: boolean, renderLeft: boolean, ignore: boolean = false): any {
    if (!ignore) {
        const top = !renderAbove ? "100%" : undefined;
        const bottom = renderAbove ? "100%" : undefined;
        const transform = renderAbove ? "translateY(-100%)" : undefined;
        const left = !renderLeft ? "0" : undefined;
        const right = renderLeft ? "0" : undefined;
        return { top, right, bottom, left, transform };
    } else {
        return undefined;
    }
}
