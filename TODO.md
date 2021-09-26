// Commend
Auto Import Class: option+commend+u or option+commend+n

# TODO 9/24
[x]  Prepare migrations
[x]  Seed the initial tags
[x]  Prepare models
[x]  Prepare Factories
[x]  Prepare resources
[x]  Tags
        - Routes
        - Controller
        - Tests
[]  Offices
        - List Offices endpoint
            [x]  Show only approved and visible records
            [x]  Filter by hosts
            [x]  Filter by users
            [x]  Include tags, images and user
            [x]  Show count of previous reservations
            [x]  Paginate
            [x]  Sort by distance if lng/lat provided, Otherwise oldest first
        - Show Office endpoint
            []  Show count of previous reservations
            []  Include tags, images and user
        - Create Office endpoint
            []  Host must be authenticated and email verified
            []  Cannot fill 'approval_status'
            []  Attach photos to offices endpoint
