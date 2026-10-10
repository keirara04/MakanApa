import Foundation

/// Centralized copy so tone stays consistent across screens. Plain English only —
/// dish names, the brand and Selera stay as they are.
enum Copy {
    /// Mealtime-aware greeting; supper covers the late-night stretch.
    static func homeGreeting(hour: Int) -> String {
        switch hour {
        case 5..<11: return "Breakfast?"
        case 11..<15: return "Lunch?"
        case 15..<18: return "Tea time?"
        case 18..<22: return "Dinner?"
        default: return "Supper?"
        }
    }
    // Onboarding
    static let onboardingHeroTitle = "Can't decide what to eat?"
    static let onboardingHeroSubtext = "Tap once and we'll pick somewhere good nearby. No endless scrolling, no group-chat debates."
    static let onboardingHeroCTA = "Get started"
    static let onboardingHaveAccount = "I already have an account"
    static let onboardingLocationTitle = "Find good food around you"
    static let onboardingLocationSubtext = "We use your location to pick places near you and show what's popular in your area."
    static let onboardingLocationFootnote = "Your location is never shown to other people."
    static let onboardingLocationConfirmed = "Location is on"
    static let onboardingHalalTitle = "Do you only eat halal?"
    static let onboardingHalalSubtext = "We'll hide places known to be non-halal. Places we haven't verified yet still show, clearly marked. Change it anytime from the map."
    static let onboardingHalalFootnote = "Halal info comes from the community. Always double-check at the restaurant."
    static let onboardingHalalYes = "Yes, hide non-halal"
    static let onboardingHalalNo = "No, show everything"

    // Ambassador
    static let ambassadorWelcomeTitle = "You're an ambassador!"
    static func ambassadorWelcomeBody(_ community: String) -> String {
        "You now represent \(community) on MakanApa. Your posts carry an Ambassador badge from today."
    }
    static let ambassadorWelcomeCTA = "Continue"
    static func ambassadorBadge(_ community: String) -> String { "Ambassador · \(community)" }
    static func ambassadorBadgeAccessibility(_ community: String) -> String { "\(community) ambassador" }

    static let ambassadorPicksTitle = "Ambassador picks"
    static func ambassadorPicksSubtitle(names: [String], community: String) -> String {
        names.count == 1 ? "Chosen by \(names[0]) for \(community)" : "Chosen by \(community)'s ambassadors"
    }
    static let ambassadorFirstPickTitle = "Add your first pick"
    static let ambassadorFirstPickDetail = "Open any place in Community or Nearby and tap Add to my picks. Members of your community will see it here."
    static let ambassadorAddPick = "Add to my picks"
    static let ambassadorInPicks = "In your picks"
    static let ambassadorPickSheetTitle = "Your pick"
    static let ambassadorPickNotePlaceholder = "What should people order? (optional)"
    static let ambassadorPickSave = "Save"
    static let ambassadorPickCancel = "Cancel"
    static let ambassadorPickRemove = "Remove from my picks"
    static let ambassadorPickFailed = "Couldn't save your pick. Try again."
    static let ambassadorPickAddPhoto = "Add your own photo of this place"
    static let googlePhotoCredit = "Google Maps"
    static let ambassadorShareCardLabel = "Your ambassador card"
    static let ambassadorShareButton = "Share card"
    static let ambassadorShareDone = "Done"
    static let ambassadorShareCardRole = "MakanApa ambassador for"
    static let ambassadorShareCardInvite = "Can't decide what to eat? Ask me, or let MakanApa pick."
    static let ambassadorShareStory = "Story"
    static let ambassadorSharePost = "Post"
    static let ambassadorShareFormatLabel = "Card format"
    static let ambassadorShareRetry = "Try again"
    static let ambassadorShareHint = "Choose a size, then share your ambassador card."
    static let ambassadorShareCardFallbackName = "MakanApa ambassador"
    static func ambassadorShareMessage(community: String) -> String {
        "I'm now MakanApa's ambassador for \(community)."
    }
    static func ambassadorShareCardAccessibility(name: String, community: String) -> String {
        "Ambassador card: \(name), MakanApa ambassador for \(community)"
    }

    // Taste prompt (after a real pick)
    static let tasteTitle = "Want sharper picks?"
    static let tasteSubtext = "Choose up to 4 things you usually go for. We'll lean that way."
    static let tasteFoodHeader = "Food"
    static let tasteStyleHeader = "Style"
    static let tasteSave = "Save"
    static let tasteNotNow = "Not now"
    static let tasteNonePicked = "None picked yet"
    static func tastePickedCount(_ count: Int, of max: Int) -> String { "\(count) of \(max) picked" }
    static func tasteLabel(_ key: String) -> String {
        switch key {
        case "malay": return "Malay"
        case "mamak": return "Mamak"
        case "korean": return "Korean"
        case "cafe": return "Cafe"
        case "dessert": return "Dessert"
        case "cheap_eats": return "Cheap eats"
        case "late_night": return "Late night"
        default: return "Anything"
        }
    }

    static let quickPickTitle = "Pick for me"
    static let chooseCravingTitle = "I know what I want"
    static let chooseCravingSubtitle = "Craving, budget, distance"
    static let quickPickHint = "Picks somewhere nearby right away using your usual budget and distance"

    static let recentTitle = "Recent"
    static let recentEmpty = "Places you pick will show up here."
    static let recentToday = "Today"
    static let recentYesterday = "Yesterday"
    static let openInMaps = "Open in Maps"
    static let removeFromRecent = "Remove from Recent"

    static let soloMoodPrompt = "What mood today?"
    static let soloMoodSubtext = "Craving something in particular?"
    static let budgetAnythingSubtext = "No limits"
    static let soloBudgetPrompt = "How much do you want to spend?"
    static let soloBudgetSubtext = "Per person"
    static let soloDistancePrompt = "How far will you go?"
    static let soloDistanceSubtext = "No sweat, we'll find something nice."
    static let soloCTA = "MAKANAPA?"

    static let moodLightSubtext = "Something lighter"
    static let moodQuickSubtext = "Fast & convenient"

    static let moodNasiKandarSubtext = "Kandar power"
    static let moodAyamGepukSubtext = "Smashed, spicy, delicious"
    static let moodNasiPadangSubtext = "Rendang, gulai, the works"
    static let moodMeeGorengSubtext = "Wok hei, always hits"
    static let moodNasiLemakSubtext = "Anytime, anywhere"
    static let moodCharKueyTeowSubtext = "Smoky and satisfying"
    static let moodBananaLeafRiceSubtext = "Drowned in curry, no regrets"
    static let moodDimSumSubtext = "Small plates, big satisfaction"
    static let moodCustomCravingPlaceholder = "Tell me what you're craving..."

    static let thinking = "Thinking so you don't have to..."
    static let thinkingStep1 = "Finding nearby spots..."
    static let thinkingStep2 = "Checking what fits you..."
    static let thinkingStep3 = "Picking the best one..."

    static let rerollHeadline = "Finding another one!"
    static let rerollLine1 = "Same preferences"
    static let rerollLine2 = "Different spot"
    static let rerollLine3 = "Still good, don't worry"

    static let noResultHeadline = "No spots found nearby"
    static let noResultDetail = "Try increasing the distance or changing your preferences."

    static let trustBadgeWhileUsing = "While using"
    static let trustBadgePrivacy = "We respect privacy"
    static let trustBadgeNearby = "Nearby spots"

    /// One label for "this is your pick" — result eyebrow and the onboarding demo badge.
    static let pickedForYou = "Picked for you"
    static let resultGo = "Let's eat"
    static let resultFatigue = "Okay, enough choosing"
    static let anythingLabel = "Anything"

    static let emptyState = "Nothing fits yet. Try increasing your distance."

    static let connectionErrorHeadline = "Oops"
    static let connectionErrorDetail = "No connection.\nCouldn't find food right now."
    static let genericAPIErrorHeadline = "Oops"
    static let genericAPIErrorDetail = "MakanApa hit a snag.\nTry again in a bit."
    static let tryAgain = "Try again"
    static let rateLimitedHeadline = "Slow down a bit"

    static func rateLimitedDetail(retryAfterSeconds: Int?) -> String {
        guard let seconds = retryAfterSeconds, seconds > 0 else {
            return "Too many tries in a row.\nTry again in a minute."
        }
        return "Too many tries in a row.\nTry again in \(seconds)s."
    }
    static let backHome = "Back home"

    static let locationDenied = "Can't find food if we don't know where you are."
    static let locationPrivacyLine = "We only use your location while you're using MakanApa."

    static let tagline = "Less thinking. More eating."
    static let aboutDescription = "MakanApa helps you decide what to eat. Tell it your mood and budget, and it picks food nearby. Less thinking, more eating."
    static let privacyPolicyURL = "https://trymakanapa.com/privacy"
    static let termsURL = "https://trymakanapa.com/terms"
    static let communityGuidelinesURL = "https://trymakanapa.com/community-guidelines"
    static let websiteURL = "https://trymakanapa.com"
    static let websiteDisplay = "trymakanapa.com"
    static let supportURL = "https://trymakanapa.com/support"

    static let loginTagline = "Eat first. Decide later."
    static let signIn = "Sign in"
    static let loginInvalidCredentials = "Email or password doesn't match."

    static let communityHeadlineUniversityFormat = "What's %@ eating?"
    static let communitySubtitleUniversityFormat = "Popular around %@"
    static let communityHeadlineAreaFormat = "What's %@ eating?"
    static let communitySubtitleAreaFormat = "Popular around %@"
    static let communityHeadlinePublic = "What's trending near you"
    static let communitySubtitlePublic = "Trending picks nearby"
    static let communityEmptyHeadline = "Nothing trending yet"
    static let communityEmptyDetail = "Every recommendation your community picks helps build this page."
    static let communityLocationPromptHeadline = "See what's trending around you"
    static let communityLocationPromptDetail = "MakanApa uses your location to find popular picks nearby."
    static let communityLocationEnable = "Continue"
    static let accountRequiredDetail = "Picks, Nearby and saves work without an account. Adding places, posting and halal reports need one, so the community knows who's contributing."
    /// The line every guest sign-in prompt carries — signing up upgrades the guest account in
    /// place, so this is literally true, not marketing.
    static let guestCarryOver = "Your picks, saved places and taste profile all come with you. Nothing lost."
    static let guestSettingsDetail = "Sign in to add places and post. Your picks, saves and taste profile come with you."
    static let nudgePicksTitle = "You've been picking well"
    static let nudgePicksDetail = "MakanApa is learning what you like. Save it to an account so a new phone doesn't mean starting over."
    static let nudgeSavesTitle = "Your food list is growing"
    static let nudgeSavesDetail = "Keep your saved places safe, on this phone or the next one."
    static let continueAsGuestFailed = "Couldn't start. Check your connection and try again."
    static let communityLocationDeniedHeadline = "Location is off"
    static let communityLocationDeniedDetail = "Turn on location access to see what's trending nearby."
    static let communityLocationOpenSettings = "Open Settings"

    // Kept short deliberately — this title sits inline between "Cancel" and this save button,
    // and a longer pair (e.g. "Choose your community" / "Save community") truncates on
    // standard-width devices. The subtitle below already spells out what this screen is for.
    static let communityChooseCommunityTitle = "Community"
    static let communityChooseCommunitySubtitle = "This helps MakanApa show what's popular around people in your community."
    static let communityChooseCommunityFooter = "You can change your community anytime."
    static let communityChooseCommunitySave = "Save"
    static let communityChangeCommunityCTA = "Change ›"
    static let communityAssignCommunityCTA = "Choose ›"
    static let communityUniversitiesSectionHeader = "UNIVERSITIES"
    static let communityAreasSectionHeader = "AREA"
    static let communityRequestUniversityRow = "Can't find your university? Request it"
    static let communityRequestAreaRow = "Can't find your area? Request it"
    // Same inline-title-truncation reasoning as communityChooseCommunityTitle above — flanked
    // by "Cancel" and "Send request", so it needs to stay short.
    static let communityRequestSheetTitle = "Request"
    static let communityRequestUniversityPlaceholder = "e.g. Universiti Malaya"
    static let communityRequestAreaPlaceholder = "e.g. Shah Alam, Cheras, your neighbourhood"
    static let communityRequestSubmit = "Send request"
    static let communityRequestSentConfirmation = "Thanks, we'll take a look!"

    // Community posts ("What KU is saying")
    static let communityPostsSectionFormat = "WHAT %@ IS SAYING"
    static let communityPostsSectionPublic = "WHAT PEOPLE ARE SAYING"
    static let communityPostsSeeAll = "See all ›"
    static let communityPostsEmptyHeadline = "No one's said anything yet"
    static let communityPostsEmptyDetailFormat = "Be the first to share what %@ is eating"
    static let communityPostsJoinPrompt = "Join a university or area community to see and share thoughts."
    static let communityPostsComposePlaceholder = "Share a thought… best teh tarik? new spot?"
    static let communityPostsReplyPlaceholder = "Write a reply…"
    static let communityPostsShareCTA = "Share a thought"
    static let communityPostsPost = "Post"
    static let communityPostsReply = "Reply"
    static let communityPostsTagPlace = "Tag a place"
    static let communityPostsTagPlaceSearch = "Search places on MakanApa"
    static let communityPostsViewAllRepliesFormat = "View all %d replies"
    static let communityPostsReportTitle = "Report post"
    static let communityPostsReportDetail = "Reports are anonymous. We review every one, and posts with several reports are hidden automatically."
    static let communityPostsReportNotePlaceholder = "Anything else we should know? (optional)"
    static let communityPostsReportSubmit = "Send report"
    static let communityPostsReportedToast = "Thanks, we'll take a look. You won't see this post again."
    static let communityPostsBlockTitleFormat = "Block %@?"
    static let communityPostsBlockDetail = "You won't see each other's posts or replies. You can unblock in Settings → Blocked users."
    static let communityPostsDeleteTitle = "Delete this post?"
    static let communityPostsGenericError = "Couldn't post that right now. Try again in a bit."
    static let communityPostsGuidelines = "Keep it friendly and about food. No links, ads or personal attacks."
    static let supportEmail = "hakeemiridza@gmail.com"

    static let communityAddPlaceMenuItem = "Add a place"
    static let communityAddPlaceCTA = "Know a good spot? Add it →"
    static let communityMyPlacesMenuItem = "My places"
    static let communitySearchTitle = "Add a place"
    static let communitySearchSubtitle = "Found somewhere good? Let's check if we know it first."
    static let communitySearchPlaceholder = "Search for a place"
    static let communitySearchExistingLabel = "Already on MakanApa"
    static let communitySearchGoogleLabel = "Found on Google"
    static let communityCantFindHeadline = "Not on Google? No problem."
    static let communityCantFindDetail = "Small places deserve to be discovered too."
    static let communityAddManually = "Add it manually →"
    static let communityDetailsTitle = "Details"
    static let communityLocationStepTitle = "Location"
    static let communityUseCurrentLocation = "Use my current location"
    static let communityChooseOnMap = "Choose on map"
    static let communityLocationReadyDetail = "We'll use where you are right now. This place should be somewhere nearby."
    static let communityLocationImmutable = "Set when this place was added. Location can't be changed."
    static let communityConfirmLocation = "Confirm this location"
    static let communityRecenterOnMe = "Recenter on me"
    static let communityReviewTitle = "Review"
    static let communitySubmitForReview = "Submit for review"
    static let communitySubmissionSuccessHeadline = "Sent for review!"
    static let communitySubmissionSuccessDetail = "We'll check the place before it shows up around MakanApa."
    static let communityMyPlacesTitle = "My places"
    static let communityStatusPending = "Pending review"
    static let communityStatusApproved = "Approved · Now discoverable on MakanApa"
    static let communityStatusChangesRequested = "Changes requested"
    static let communityStatusRejected = "Not approved"
    static let communityStatusCancelled = "Cancelled"
    static let communityUpdateSubmission = "Update submission"
    static let communityCancelSubmission = "Cancel submission"

    static let communityStepFormat = "Step %d of %d · %@"
    static let communityDetailsHeadline = "Tell us about this place"
    static let communitySpendFooter = "Usually around how much per person?"
    static let communitySpendErrorInline = "Enter an estimated spend per person."
    static let communityAddMoreDetailsTitle = "Add more details"
    static let communityAddMoreDetailsSubtitle = "Address, phone & socials"
    static let communityAddMenuTitle = "Add menu"
    static let communityAddMenuSubtitle = "Help people know what's good here."
    static let communityBlankIsFine = "You can leave these blank. The community can help complete them later."
    static let communityAddMenuItemCTA = "Add another item"

    // MARK: - Add a place

    static let communitySearchNeedsLocation = "Turn on location to also search Google."
    static let communitySearchFailed = "Couldn't search right now. Check your connection and try again."
    static let communitySpendEstimated = "Estimated from Google. Change it if you know better."
    static let communityEditNothingChanged = "Change something, or add a note or photo, to send this edit."
    static let communityEditNoChanges = "Nothing changed yet"
    static let communityLocationWaiting = "Getting your location…"
    static let communityLocationOff = "Location is off for MakanApa. Turn it on in Settings, or choose the spot on the map."
    static let communityLocationUnavailable = "Couldn't get your location. Try again, or choose the spot on the map."

    // MARK: - Saved: search and save

    static let savedSearchPrompt = "Search places to save"
    static let savedSearchYourPlaces = "Your saved places"
    static let savedSearchSaveHeader = "Save a place"
    static func savedSearchNoResults(_ query: String) -> String { "No places match \"\(query)\" around you." }
    static let savedSearchNeedsLocation = "Turn on location to search places around you."
    static let savedSearchSaveFailed = "Couldn't save that place. Try again."
    static let savedSearchAddNew = "Can't find it? Add it as a new place"
    static let savedSearchAddNewDetail = "We'll check MakanApa and Google first. New places show up after a quick review."
    static let savedEmptyTitle = "No saved places yet"
    static let savedEmptyDetail = "Tap the heart on a place in Nearby, or search above to save one."
    static let savedStateSaved = "Saved"
    static let savedStateNotSaved = "Not saved"
    static let savedToggleHint = "Saves or removes this place"

    // MARK: - Picture guides (shared)

    static let guideNext = "Next"
    static let guideDone = "Got it"
    static let guidePageFormat = "Page %d of %d"

    // MARK: - Decide guide

    static let decideGuideTitle = "How Decide works"
    static let decideGuideFooterTitle = "New to MakanApa?"
    static let decideGuideFooterDetail = "See how picking works."
    static let decideGuideQuickTitle = "Pick for me"
    static let decideGuideQuickBody = "One tap and Bubu picks somewhere nearby, using the budget and distance you used last time."
    static let decideGuideCravingTitle = "I know what I want"
    static let decideGuideCravingBody = "Choose a mood or type a craving, set your budget and distance, and MakanApa picks for you."
    static let decideGuideWhyTitle = "See why it won"
    static let decideGuideWhyBody = "Every pick tells you why it was chosen. Tap Let's eat for directions, or ask for another one."
    static let decideGuideTuneTitle = "Nudge the pick"
    static let decideGuideTuneBody = "Not quite right? Tap Closer, Cheaper or Safer bet and it picks again from the same list."
    static let decideGuideSavedTitle = "Pick from your saved places"
    static let decideGuideSavedBody = "Save two or more places and MakanApa shuffles them like a deck of cards and deals you one."
    static let decideGuideRecentTitle = "Go back to a favourite"
    static let decideGuideRecentBody = "Places you pick show up in Recent. Tap one to open it in Maps, or press and hold to remove it."

    // MARK: - Nearby guide

    static let nearbyGuideTitle = "How Nearby works"
    static let nearbyGuideMapTitle = "Move around the map"
    static let nearbyGuideMapBody = "Each pin is a place with its rating. Move the map, then tap Search this area to load what's there."
    static let nearbyGuideFilterTitle = "Filter what you see"
    static let nearbyGuideFilterBody = "Hide non-halal places, show only what's open, or keep it under RM 20. Places that don't fit fade out."
    static let nearbyGuideModeTitle = "Try a mode"
    static let nearbyGuideModeBody = "Switch between For you, Popular, Low-key and more to see different kinds of places."
    static let nearbyGuideSearchTitle = "Search a dish or a place"
    static let nearbyGuideSearchBody = "Tap the search button and type a dish or a name. Results show on the map and in a list."
    static let nearbyGuidePlaceTitle = "Open a place"
    static let nearbyGuidePlaceBody = "Tap a pin for details. Tap the heart to save it, or Eat here when you've decided."
    static let nearbyGuidePickTitle = "Let Bubu pick"
    static let nearbyGuidePickBody = "Can't choose? Tap Pick for me and Bubu picks one of the places on the map. Pull up Around here for the full list."
    static let nearbyGuideSearchThisArea = "Search this area"
    static let nearbyGuideChipHalal = "Hide non-halal"
    static let nearbyGuideChipOpen = "Open now"
    static let nearbyGuideChipBudget = "≤ RM20"
    static let nearbyGuideModeForYou = "For you"
    static let nearbyGuideModeLowKey = "Low-key"
    static let nearbyGuideModeCafe = "Cafe"
    static let nearbyGuideSearchExample = "nasi lemak"

    // MARK: - Become an ambassador

    static let ambassadorApplyRowTitleFormat = "Become an ambassador for %@"
    static let ambassadorApplyRowDetail = "Pick the best spots for your community."
    static let ambassadorApplyTitle = "Become an ambassador"
    static let ambassadorApplyHeadlineFormat = "Represent %@ on MakanApa"
    static let ambassadorApplyPerkPicksFormat = "Hand-pick the best places to eat around %@"
    static let ambassadorApplyPerkSeenFormat = "Everyone in %@ sees your picks in Community"
    static let ambassadorApplyPerkCard = "Get your own ambassador card to share"
    static let ambassadorApplyReasonLabel = "Why you?"
    static let ambassadorApplyReasonPlaceholder = "How well do you know the food around here?"
    static let ambassadorApplyReasonFooter = "At least 20 characters."
    static let ambassadorApplyInstagramLabel = "Instagram (optional)"
    static let ambassadorApplyInstagramPlaceholder = "@handle"
    static let ambassadorApplySend = "Send application"
    static let ambassadorApplySentTitle = "Application sent"
    static let ambassadorApplySentDetail = "The team will look at it soon, and you'll get a notification when they do."
    static let ambassadorApplyApprovedTitleFormat = "You're the ambassador for %@"
    static let ambassadorApplyApprovedDetail = "Your ambassador tools are now in Community."
    static let ambassadorApplyDeclinedTitle = "Not this time"
    static let ambassadorApplyDeclinedDetail = "Your last application wasn't approved, but you're welcome to apply again."
    static let ambassadorApplyLoadFailed = "Couldn't check your application."
    static let ambassadorApplySendFailed = "Couldn't send it. Try again in a bit."
    static let ambassadorApplyCountFormat = "%d/500"

    // MARK: - My places

    static let myPlacesStatLive = "Live"
    static let myPlacesStatReview = "In review"
    static let myPlacesStatChanges = "Needs changes"
    static let myPlacesFilterAll = "All"
    static let myPlacesFilterLabel = "Show"
    static let myPlacesTypeNew = "New place"
    static let myPlacesTypeEdit = "Edit"
    static let myPlacesTypeHalal = "Halal report"
    static let myPlacesTypeOwner = "Ownership claim"
    static let myPlacesTypeClosure = "Closure report"
    static let myPlacesTypeReopen = "Reopen report"
    static let myPlacesStepSent = "Sent"
    static let myPlacesStepReviewing = "Reviewing"
    static let myPlacesStepLive = "Live"
    static let myPlacesReviewerNote = "Reviewer's note"
    static let myPlacesEmptyTitle = "Nothing here yet"
    static let myPlacesEmptyDetail = "Places you add or fix show up here, with where they are in review."
    static let myPlacesEmptyFiltered = "Nothing in this list right now."
    static let myPlacesAddPlace = "Add a place"
    static let myPlacesLoadFailed = "Couldn't load your places."
    static let myPlacesWithdraw = "Withdraw"
    static let myPlacesWithdrawTitle = "Withdraw this submission?"
    static let myPlacesWithdrawMessage = "It won't be reviewed. You can always add it again."
    static let myPlacesWithdrawFailed = "Couldn't withdraw it. Try again."

    // MARK: - Community guide

    static let communityGuideTitle = "How Community works"
    static let communityGuideFooterTitle = "New to Community?"
    static let communityGuideFooterDetail = "See how to add places, post and react."
    static let communityGuideCommunityTitle = "Pick your community"
    static let communityGuideCommunityBody = "Tap the name at the top to switch between your university, your area, or everyone nearby."
    static let communityGuideAddTitle = "Add a place"
    static let communityGuideAddBody = "Tap + and choose Add a place. Search first: if it's already listed you can suggest an edit, and if it's on Google we fill in the details for you."
    static let communityGuideTrackTitle = "Track what you added"
    static let communityGuideTrackBody = "Tap + and choose My places to see what's in review and what's live. We'll notify you once it's checked."
    static let communityGuidePostTitle = "Share a thought"
    static let communityGuidePostBody = "Tap Share a thought under the posts. Tag a place so others can open it straight from your post."
    static let communityGuideReactTitle = "React and reply"
    static let communityGuideReactBody = "Tap a reaction under any post, or the speech bubble to reply."
    static let communityGuidePlaceTitle = "Open any place"
    static let communityGuidePlaceBody = "Tap a place for directions, photos and the menu. You can add photos or menu items, or help confirm if it's halal."
    static let communityGuideSafetyTitle = "Keep it friendly"
    static let communityGuideSafetyBody = "Tap the three dots on a post to report it or block someone. Our team reviews every report."
    static let communityGuideStatusReview = "In review"
    static let communityGuideStatusLive = "Live"
    static let communityGuideReport = "Report"
    static let communityGuideBlock = "Block"
    static let communityEmptyAddTitle = "Know a spot worth sharing?"
    static let communityEmptyAddDetail = "Add it so your community can find it."

    // MARK: - Pick from saved places

    static let savedPickTitle = "Pick from my saved"
    static func savedPickSubtitle(count: Int) -> String { "Shuffle your \(count) saved places" }
    static let savedPickHint = "Shuffles your saved places and picks one for you"
    static let savedPickButton = "Pick one for me"
    static let savedPickNeedMore = "Save one more place and MakanApa can pick between them."
    static let savedPickShuffling = "Shuffling your saved places…"
    static let savedPickDealt = "Here's your pick"
    static let savedPickNoneAvailable = "None of your saved places can be picked right now. They may be closed, or hidden by Halal-only."
    static let savedPickFailed = "Couldn't pick right now. Check your connection and try again."
    static let savedPickRateLimited = "That's a lot of shuffling. Try again in a minute."
    static let savedPickCardBackLabel = "Saved place, face down"
    static let savedPickRevealing = "And the pick is…"
    static let savedPickWhyTitle = "Why this one"
    static let savedPickShuffleAgain = "Shuffle again"
    static let savedPickNoMore = "That was every saved place that fits right now."
    static let savedPickReasonSaved = "One of your saved places"
    static func savedPickReasonSavedAgo(_ relative: String) -> String { "You saved it \(relative)" }
    static func savedPickReasonDistance(_ distance: String) -> String { "\(distance) from you" }
    static let savedPickReasonOpen = "Open right now"
    static func savedPickWalk(minutes: Int) -> String { "\(minutes) min walk" }
    static func savedPickAway(_ distance: String) -> String { "\(distance) away" }
    static let savedPickMapLabel = "Map of where it is"
    static func savedPickInDeck(_ names: [String], more: Int) -> String {
        let list = names.joined(separator: ", ")
        return more > 0 ? "In the deck: \(list) + \(more) more" : "In the deck: \(list)"
    }
    static let close = "Close"
    static let back = "Back"
    static let cancel = "Cancel"

    // MARK: - Pick explanation

    static let whyThisPick = "Why this one?"
    static let whyNotPrompt = "Help me learn: why not this one?"
    static let pickingInProgress = "Bubu's thinking..."
    static let eatHere = "Eat here"
}
