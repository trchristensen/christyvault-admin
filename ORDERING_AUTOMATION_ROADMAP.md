# Customer Ordering and Delivery Automation Roadmap

This note captures the future ordering, load-planning, delivery, and AI automation ideas discussed while building the order truck-fit preview and load summary.

It is a product direction document, not a commitment that every feature must be built exactly as described. The operating rules should be proven internally before they are exposed to customers or automated through AI.

## Long-Term Goal

Create a dependable ordering and scheduling system that allows routine business to continue without Todd or the office manager personally handling every call, email, order, and delivery decision.

Customers should eventually be able to:

- Place orders through a web portal.
- Repeat a common order or saved full-load template.
- See whether an order fits on the selected truck.
- Receive help completing an efficient full truckload.
- View valid delivery choices and rates for their location.
- Request a flexible/shared delivery when a dedicated load does not make sense.
- Submit orders by email or telephone through an AI assistant using the same business rules and APIs as the portal.

The target operating model is exception-based: routine orders proceed automatically, while ambiguous or unusual orders are sent to a small review queue.

## Core Product Principles

### The business system is authoritative

AI may conduct the conversation, but it must not invent quantities, prices, dates, products, truck capacity, or loading decisions. It should use structured application services and APIs for every material decision.

### Loading and scheduling remain deterministic

Physical capacity, placement, weight, truck eligibility, production availability, scheduling, and delivery rules must come from the application's rule engines—not an AI model's judgment.

### Never recommend random products

Suggestions should be limited to products that:

- The customer commonly purchases.
- Are part of a saved or approved customer load.
- Are explicitly configured as eligible fill products.
- Are recognized upgrades within a product family the customer already purchases.

Historical ordering behavior should rank valid suggestions. It must never override loading, safety, operational, or customer-specific rules.

### Never silently change an order

The system may preview additions, substitutions, upgrades, or delivery options, but the customer or authorized office user must explicitly apply and confirm them.

### Office overrides remain available

Automation should handle normal cases while allowing authorized users to override truck selection, scheduling, load placement, and delivery eligibility when a real-world exception requires it. Overrides should be recorded in an audit trail.

## Near-Term Feature: Complete This Load

The next major improvement to the live order diagram should be a **Complete This Load** assistant.

Instead of merely reporting remaining capacity, it should create two or three practical full-load scenarios:

1. **More of the same**
   - Increase products already present on the order.

2. **Typical customer load**
   - Use the customer's historical product mix or a saved load template.

3. **Custom approved mix**
   - Allow the office or customer to select eligible products, then optimize their quantities.

Each scenario should clearly display:

- Products and quantities that would be added.
- Resulting total weight.
- Physical racks used.
- Rack positions and placement.
- Pallets and direct flatbed positions used.
- Remaining capacity.
- Any manual-review warnings.
- Whether all hard loading and delivery rules are satisfied.

The user should be able to preview a scenario and deliberately choose **Apply this load**.

### Definition of a full load

A truck does not need to reach its exact maximum weight to be operationally full. A successful full load should:

1. Use the practical rack and flatbed capacity efficiently.
2. Remain safely below the truck's weight limit.
3. Satisfy all rack, pallet, stop, and placement rules.
4. Contain products appropriate for that customer.
5. Avoid unnecessary manual placement or awkward unloading.

## Customer Ordering Profiles

With approximately 164 customers—and a large share of business concentrated among a small group—customer-specific configuration is more useful than a broad machine-learning recommendation system.

A future Customer Ordering Profile may contain:

- Authorized ordering contacts.
- Approved delivery locations.
- Originating plant.
- Frequently ordered products.
- Approved fill products.
- Approved upgrade paths.
- Saved full-load templates.
- Typical quantities and product combinations.
- Preferred truck and delivery type.
- Normal delivery days and required notice.
- Site access, unloading, and special delivery instructions.
- Pricing terms.
- Purchase-order requirements.
- Credit restrictions.
- Permitted substitutions.
- Preferred email, portal, or telephone communication.

The most important customers can be configured deliberately. General rules can serve less-frequent customers until useful ordering history exists.

## Product Recommendations and Upselling

Load optimization and merchandising should be separate concerns.

### Load optimizer

Determines which combinations are physically, operationally, and safely valid.

### Product recommender

Ranks only valid options based on:

1. Products already on the current order.
2. Products the customer regularly purchases.
3. Saved load templates.
4. Common companion products.
5. Approved higher-end alternatives in a familiar product family.

Potential upgrades should never create an awkward load or reduce operational safety merely because they are more profitable.

## Delivery Choices

The future portal should present clear delivery choices based on the customer's destination, order, truck capacity, and current operating rules.

### Full Truckload

- Provides a firm delivery date.
- Rate is based on destination or delivery zone.
- The customer may use the Complete This Load assistant.
- A customer can still choose to pay for dedicated capacity without filling every position.

### Smaller Truck

- Offered only when the order physically fits.
- Subject to a configurable maximum distance from the originating plant.
- Generally discouraged or unavailable for long-distance deliveries.
- Authorized office users retain an override.

### Flex Delivery

Use **Flex Delivery** as the customer-facing name. Internally, the resulting trip may be a split/multi-stop load.

- Intended for orders that do not justify a dedicated truck.
- Generally discouraged and not guaranteed.
- May be selectively enabled during slow periods.
- Does not receive a firm delivery date until a compatible match is approved.
- Requires an acceptable delivery window rather than a single requested date.
- Every final match should initially require office approval.

Customers must not see another customer's identity, products, pricing, or order details.

## Flex Delivery Windows

Flex Delivery should not necessarily operate as a permanently open marketplace. The office may open controlled capacity windows when additional business is desirable.

An open Flex window could define:

- Originating plant.
- Geographic region or route corridor.
- Earliest and latest delivery dates.
- Target combined truck utilization.
- Maximum route detour.
- Maximum number of stops.
- Eligible truck configurations.
- Minimum order contribution or delivery charge.
- Customer response deadline.

Example customer language:

> Flex Delivery — availability varies. Allow us to combine your order with another delivery in your region. Select an acceptable delivery window, and we will confirm a date if a compatible route becomes available.

### Avoiding uneconomical small deliveries

Eligibility should not be decided by product count alone. The system should evaluate:

- Rack and pallet capacity consumed.
- Total and incremental weight.
- Delivery revenue or minimum delivery charge.
- Additional driving miles and time.
- Unloading time.
- Route direction and detour.
- Final combined truck utilization.

A small order may be reasonable when the truck already passes near the customer. The same order may be rejected when it requires a large detour.

### Preventing indefinite limbo

A Flex request should include:

- Earliest acceptable delivery date.
- Latest acceptable delivery date.
- Required advance notice.
- A matching expiration or decision date.

If no match is found, offer:

- Add products and convert to a full truckload.
- Pay for dedicated remaining capacity.
- Extend the Flex window.
- Contact the office for another arrangement.

## Compatible Flex Match Requirements

A valid shared-delivery match should consider:

- Same originating plant.
- Overlapping delivery windows.
- Compatible direction or route corridor.
- Maximum allowable detour.
- Compatible truck configuration.
- Weight, racks, rack levels, pallets, and flatbed capacity.
- Product-placement and handling rules.
- Correct stop and unloading order.
- Stop-separation preferences.
- Maximum permitted number of stops.
- Products or customers that must not share a trip.
- Production readiness.
- Driver and truck availability.

## Order and Delivery Orchestration API

The portal, email AI, and telephone AI should all use one structured orchestration layer:

```text
Customer portal ─┐
Email AI ─────────┼── Order orchestration API ── Existing order system
Telephone AI ─────┘
```

Potential API capabilities:

- Identify and authenticate a customer/contact.
- Retrieve approved customer locations.
- Retrieve frequent products and saved loads.
- Create and modify a draft order.
- Calculate order and trip truck fit.
- Generate complete-load scenarios.
- Validate production lead time and availability.
- Determine valid truck and delivery options.
- Calculate delivery pricing.
- Find available delivery dates or Flex windows.
- Read back a complete order for confirmation.
- Record explicit customer confirmation.
- Submit, schedule, or escalate the order.

The API should return structured reasons whenever an order cannot be completed automatically.

## AI Email and Telephone Ordering

AI should act as a conversational order clerk using the orchestration API.

A future interaction might be:

> I found Chico Cemetery. Your typical full load is six G5s and six V1s. Would you like to repeat that load or make changes?

The AI may collect information and explain system-calculated choices, but it should:

- Never guess when customer identity or intent is unclear.
- Never substitute a product without permission.
- Never promise an unavailable delivery date.
- Never bypass credit, pricing, production, or delivery restrictions.
- Read back the final products, quantities, destination, date or delivery window, and price.
- Obtain explicit confirmation before submission.
- Preserve a transcript and audit trail.
- Escalate unclear or exceptional cases.

## Vacation-Ready Exception Model

The system is vacation-ready when normal orders can proceed without routine office interpretation.

### Eligible for automatic processing

- Known authorized customer/contact.
- Approved delivery location.
- Familiar or approved products.
- Valid loading profile and truck fit.
- Valid production lead time.
- Available truck, driver, and delivery date.
- Pricing and account terms resolve correctly.
- No credit or policy hold.
- Customer explicitly confirms the final order.

### Requires review

- Unknown caller or email sender.
- New delivery location.
- Custom or unfamiliar product.
- Missing loading profile or shipping weight.
- Manual load placement required.
- Conflicting delivery or production capacity.
- Flex match awaiting approval.
- Unusual substitution, pricing, or discount.
- Credit problem.
- Ambiguous instructions.
- Requested override of a normal operating rule.

Todd and the office manager should receive a concise exception queue and summary rather than having to touch every order.

## Suggested Implementation Sequence

1. Build and test the internal **Complete This Load** assistant.
2. Add Customer Ordering Profiles and saved load templates.
3. Formalize production availability, truck eligibility, delivery rates, and scheduling rules.
4. Create a structured draft-order and confirmation API.
5. Build an internal Flex matching screen and test matches manually.
6. Build the customer ordering portal on the same API.
7. Add AI email intake.
8. Add AI telephone ordering.
9. Expand automatic confirmation only after routine scenarios have proven dependable.

## Important Open Decisions

- How should a practical full load be scored when weight and physical capacity disagree?
- Which products are valid fill products for each customer?
- Which upgrades may be suggested, and to whom?
- What distance limit applies to each smaller-truck configuration and plant?
- How are delivery rates calculated by location, truck, and delivery method?
- What minimum economics justify a Flex stop?
- How much detour is acceptable for different order sizes?
- What utilization target is required before a Flex trip is confirmed?
- Which products or customers must never share a trip?
- Which orders may auto-confirm versus always requiring review?
- What payment, PO, and credit checks are required before confirmation?
- What production-capacity information must be available before promising a date?

