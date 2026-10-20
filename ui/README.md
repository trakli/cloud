# Trakli billing UI layer

Include this directory in the host web UI's `TRAKLI_UI_LAYERS` setting and enable the matching backend plugin. The layer supplies the authenticated `/billing` page, English messages and billing flow tests.

Yearly billing is selected by default. Checkout and portal returns retain the existing billing address. Bank management is shown only when a bank UI layer supplies its route.

Run the host web UI's lint, format, tests and build with this layer enabled. The host provides authentication, API access, components and layout.
