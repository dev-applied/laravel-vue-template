import RouteDesigner from '@/router/RouteDesigner'
import Authentication from '@/middleware/Authentication'
import Authorization from '@/middleware/Authorization'
import ForceTypes from "@/middleware/ForceTypes";

RouteDesigner.setNotFound('Error404', {layout: 'Empty'})

RouteDesigner.group({middleware: [ForceTypes]}, function () {
    RouteDesigner.group({ layout: 'Empty' }, function() {
        RouteDesigner.route('/', 'LoginPage', 'Login')
    })

    RouteDesigner.group({
        middleware: [Authentication, Authorization],
        prefix: '/:store_id?',
        layout: 'Default'
    }, function() {
        RouteDesigner.route('/dashboard', 'DashboardPage', 'Dashboard')
    })
})
