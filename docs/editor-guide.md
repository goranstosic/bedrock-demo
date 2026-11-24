# Editor guide

For the people who write and publish content on the Northwind site. No technical knowledge needed.

## The content you will work with

| Type | Find it in the admin under | Used for |
| --- | --- | --- |
| Sectors | **Sectors** | One page per industry we serve, at `/sectors/<name>/` |
| Client stories | **Client stories** | Case studies, at `/client-stories/<name>/` |
| Pages | **Pages** | Everything else, such as the home page |

## Writing a client story

1. Go to **Client stories > Add new client story**.
2. Write a title that states the result: "Harbour Bank cuts onboarding time by 60%" works better than "Harbour Bank".
3. Fill in the **Excerpt** (in the sidebar, under the post settings). It is the short summary shown on the story cards, so keep it to one or two sentences.
4. In the **Sectors** field below the content, choose every sector the story belongs to. You do not need to edit the sector as well: the link appears on both sides automatically.
5. In the **Regions** box in the sidebar, tick where the story should appear:
   - **Global** shows it to every visitor, whatever region they choose.
   - **United States**, **United Kingdom** or **Australia** shows it only to visitors who chose that region.
   - You can tick more than one.
6. Publish.

A story with no region ticked is not shown on any sector page, so always tick at least one.

## How regions work for visitors

The **Region** links in the site header let visitors choose Global, United States, United Kingdom or Australia. Their choice follows them from page to page.

On a sector page, the client stories list shows stories tagged with the visitor's region plus all Global stories. If a sector has no stories for that region, the list is left out of the page entirely, rather than showing an empty box. To check what a visitor in another region sees, use the Region links on the live page.

## Blocks you can use

Find them in the block inserter (the **+** button) under **Northwind**.

### Stats

A row of up to four headline figures.

- **Number**: the figure itself, for example `60%` or `4.2m`. Keep it short.
- **Label**: what the number means, for example "Faster customer onboarding".
- **Source** (optional): where the figure comes from. Add one whenever a figure could be questioned.

If no figure is filled in, the block does not appear on the page; in the editor you will see a grey hint instead.

### Client Story Cards

Cards linking to the client stories of a sector.

- **Heading**: defaults to "Client stories" if left empty.
- **Sector**: which sector's stories to show. On a sector page you can leave this empty and it uses that sector.

The cards follow the visitor's region (see above). Sector pages already include this block at the bottom, so you do not need to add it there.

### Region Switcher

The region links in the header. It is already in place; you do not need to add it anywhere.

## Patterns

Patterns are ready-made combinations of blocks. Open the block inserter, choose the **Patterns** tab, then the **Northwind** category.

- **Sector proof**: Stats followed by Client Story Cards. Good for the end of a sector page or a campaign page. Replace the sample figures after inserting it.

**Appearance > Pattern library** lists every pattern with a description and the blocks it uses.

## Writing for everyone

The site aims to meet WCAG 2.2 AA. You can help:

- Use headings in order: the page title is the only Heading 1; sections start at Heading 2.
- Write link text that makes sense on its own: "Read the Harbour Bank story", not "Click here".
- Give every informative image alternative text that says what it shows. Leave it empty only for purely decorative images.
- Keep sentences short and use plain words.

## Redirects

When a page is moved or renamed, ask the development team to add a redirect. Redirects for site migrations are kept in a reviewed file in the code repository, so they are the same on staging and production. You can see them under **Tools > Redirection**, in the group "Northwind imports". Please do not edit the redirects in that group by hand; the next import puts them back the way the file says. Redirects you add to other groups are left alone.
